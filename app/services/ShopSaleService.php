<?php
/**
 * GiftAura - sales made in the physical shop (walk-in customers).
 *
 * The administrator (or staff with the "shop_sales" area) picks products
 * and quantities; the system:
 *   - reads the CURRENT price of each product from the database (the
 *     browser never sends prices), so the invoice matches the website;
 *   - locks the products and reduces stock through InventoryService, in
 *     one transaction, so a product can never be sold twice when only one
 *     is left (website order and shop sale at the same moment);
 *   - saves the invoice (shop_sales + shop_sale_items, a snapshot of names
 *     and prices) with a reference number INV-YYMMDD-0001.
 * A mistaken invoice can be cancelled ("voided"): the stock goes back and
 * the invoice stays in the list, marked Cancelled, so nothing disappears.
 */
declare(strict_types=1);

final class ShopSaleService
{
    public const PAYMENT_METHODS = ['cash' => 'Cash', 'card' => 'Card', 'bank_transfer' => 'Bank transfer'];
    public const MAX_LINES = 30;
    public const MAX_QTY = 999;
    public const PER_PAGE = 25;

    /** Products that can be sold now: in stock, A–Z (inactive ones are marked, they can still be sold in the shop). */
    public static function sellableProducts(): array
    {
        return Database::fetchAll(
            'SELECT id, name, sku, price, stock_quantity, is_active FROM products
              WHERE stock_quantity > 0 ORDER BY name, id'
        );
    }

    /**
     * Record a sale. Input: product_id[] and quantity[] (parallel arrays),
     * customer_name, customer_phone, discount, payment_method,
     * amount_received, note.
     * Returns ['id' => int] or ['errors' => [...], 'old' => [...]].
     */
    public static function create(array $input, int $adminId): array
    {
        $errors = [];
        $old = [
            'customer_name'   => mb_substr(trim((string) ($input['customer_name'] ?? '')), 0, 100),
            'customer_phone'  => trim((string) ($input['customer_phone'] ?? '')),
            'discount'        => trim((string) ($input['discount'] ?? '')),
            'payment_method'  => (string) ($input['payment_method'] ?? 'cash'),
            'amount_received' => trim((string) ($input['amount_received'] ?? '')),
            'note'            => mb_substr(CartService::cleanMessage((string) ($input['note'] ?? '')), 0, 255),
            'lines'           => [],
        ];

        // Lines: merge the same product chosen twice; ignore empty rows.
        $ids = is_array($input['product_id'] ?? null) ? array_values($input['product_id']) : [];
        $qtys = is_array($input['quantity'] ?? null) ? array_values($input['quantity']) : [];
        $wanted = [];
        foreach ($ids as $i => $rawId) {
            $rawId = trim((string) $rawId);
            $rawQty = trim((string) ($qtys[$i] ?? ''));
            if ($rawId === '' && $rawQty === '') {
                continue;
            }
            $old['lines'][] = ['product_id' => $rawId, 'quantity' => $rawQty];
            if (!ctype_digit($rawId) || (int) $rawId === 0) {
                $errors['lines'] = 'Choose a product on every line you filled in.';
                continue;
            }
            if (!preg_match('/^\d{1,3}$/', $rawQty) || (int) $rawQty < 1) {
                $errors['lines'] = 'Each quantity must be a whole number from 1 to ' . self::MAX_QTY . '.';
                continue;
            }
            $wanted[(int) $rawId] = ($wanted[(int) $rawId] ?? 0) + (int) $rawQty;
        }
        if ($wanted === [] && !isset($errors['lines'])) {
            $errors['lines'] = 'Add at least one product.';
        }
        if (count($wanted) > self::MAX_LINES) {
            $errors['lines'] = 'One invoice can have up to ' . self::MAX_LINES . ' different products.';
        }

        if ($old['customer_phone'] !== '') {
            $phone = Validator::normalisePhone($old['customer_phone']);
            if ($phone === null) {
                $errors['customer_phone'] = 'Enter a valid Sri Lankan phone number, e.g. 0771234567, or leave it empty.';
            } else {
                $old['customer_phone'] = $phone;
            }
        }
        if (!isset(self::PAYMENT_METHODS[$old['payment_method']])) {
            $errors['payment_method'] = 'Choose how the customer paid.';
        }
        $discountCents = 0;
        if ($old['discount'] !== '') {
            $discountCents = self::cents($old['discount']);
            if ($discountCents === null) {
                $errors['discount'] = 'Enter the discount in rupees, e.g. 250 or 250.50, or leave it empty.';
                $discountCents = 0;
            }
        }
        $receivedCents = null;
        if ($old['amount_received'] !== '' && $old['payment_method'] === 'cash') {
            $receivedCents = self::cents($old['amount_received']);
            if ($receivedCents === null) {
                $errors['amount_received'] = 'Enter the cash received in rupees, or leave it empty.';
            }
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'old' => $old];
        }

        try {
            $id = Database::transaction(static function () use ($wanted, $old, $discountCents, $receivedCents, $adminId): int {
                $in = [];
                $params = [];
                foreach (array_keys($wanted) as $i => $productId) {
                    $in[] = ':p' . $i;
                    $params['p' . $i] = $productId;
                }
                // Lock the rows so a website order cannot take the same stock at the same moment.
                $rows = Database::fetchAll(
                    'SELECT id, name, sku, price, stock_quantity FROM products WHERE id IN (' . implode(', ', $in) . ') ORDER BY id FOR UPDATE',
                    $params
                );
                $products = [];
                foreach ($rows as $row) {
                    $products[(int) $row['id']] = $row;
                }
                $lines = [];
                $subtotalCents = 0;
                foreach ($wanted as $productId => $qty) {
                    $p = $products[$productId] ?? null;
                    if ($p === null) {
                        throw new DomainException('A product on this invoice no longer exists. Please choose again.');
                    }
                    if ((int) $p['stock_quantity'] < $qty) {
                        throw new DomainException('Not enough stock for ' . $p['name'] . ': ' . (int) $p['stock_quantity'] . ' left, ' . $qty . ' wanted.');
                    }
                    $unit = self::cents((string) $p['price']) ?? 0;
                    $lines[] = ['product' => $p, 'qty' => $qty, 'unit' => $unit, 'total' => $unit * $qty];
                    $subtotalCents += $unit * $qty;
                }
                if ($discountCents > $subtotalCents) {
                    throw new DomainException('The discount (' . money(self::rupees($discountCents)) . ') cannot be more than the subtotal (' . money(self::rupees($subtotalCents)) . ').');
                }
                $totalCents = $subtotalCents - $discountCents;
                if ($receivedCents !== null && $receivedCents < $totalCents) {
                    throw new DomainException('The cash received is less than the total of ' . money(self::rupees($totalCents)) . '.');
                }

                $now = date('Y-m-d H:i:s');
                $saleId = Database::insert(
                    'INSERT INTO shop_sales (invoice_number, customer_name, customer_phone, subtotal, discount, total, payment_method,
                                             amount_received, note, created_by, created_at)
                     VALUES (:inv, :name, :phone, :sub, :disc, :total, :method, :received, :note, :by, :now)',
                    [
                        'inv' => 'TMP-' . bin2hex(random_bytes(6)),
                        'name' => $old['customer_name'] !== '' ? $old['customer_name'] : null,
                        'phone' => $old['customer_phone'] !== '' ? $old['customer_phone'] : null,
                        'sub' => self::rupees($subtotalCents), 'disc' => self::rupees($discountCents), 'total' => self::rupees($totalCents),
                        'method' => $old['payment_method'],
                        'received' => $receivedCents !== null ? self::rupees($receivedCents) : null,
                        'note' => $old['note'] !== '' ? $old['note'] : null,
                        'by' => $adminId, 'now' => $now,
                    ]
                );
                $invoice = self::invoiceNumber($saleId, $now);
                Database::execute('UPDATE shop_sales SET invoice_number = :inv WHERE id = :id', ['inv' => $invoice, 'id' => $saleId]);

                foreach ($lines as $line) {
                    Database::execute(
                        'INSERT INTO shop_sale_items (sale_id, product_id, product_name, sku, unit_price, quantity, line_total)
                         VALUES (:sale, :pid, :name, :sku, :unit, :qty, :total)',
                        [
                            'sale' => $saleId, 'pid' => (int) $line['product']['id'], 'name' => $line['product']['name'],
                            'sku' => $line['product']['sku'], 'unit' => self::rupees($line['unit']), 'qty' => $line['qty'],
                            'total' => self::rupees($line['total']),
                        ]
                    );
                    InventoryService::adjust((int) $line['product']['id'], -$line['qty'], 'shop_sale', null, $adminId, $invoice, $saleId);
                }
                ActivityLogService::log($adminId, 'shop_sale.create', 'shop_sale', $saleId,
                    $invoice . ': ' . money(self::rupees($totalCents)) . ' (' . self::PAYMENT_METHODS[$old['payment_method']] . ')');
                return $saleId;
            });
        } catch (DomainException $e) {
            return ['errors' => ['lines' => $e->getMessage()], 'old' => $old];
        } catch (RuntimeException $e) {
            // InventoryService: stock changed between the check and the update.
            return ['errors' => ['lines' => 'The stock of a product changed while saving. Please check the quantities and try again.'], 'old' => $old];
        }
        Logger::info('Shop sale recorded', ['sale_id' => $id]);
        return ['id' => $id];
    }

    /** INV-261024-0007: the date of the sale and the sale number. */
    public static function invoiceNumber(int $id, string $createdAt): string
    {
        return 'INV-' . date('ymd', strtotime($createdAt) ?: time()) . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    public static function find(int $id): ?array
    {
        return Database::fetchOne(
            'SELECT s.*, u.full_name AS cashier_name, v.full_name AS voided_by_name
               FROM shop_sales s
               LEFT JOIN users u ON u.id = s.created_by
               LEFT JOIN users v ON v.id = s.voided_by
              WHERE s.id = :id',
            ['id' => $id]
        );
    }

    public static function items(int $saleId): array
    {
        return Database::fetchAll('SELECT * FROM shop_sale_items WHERE sale_id = :id ORDER BY id', ['id' => $saleId]);
    }

    /** Filters: from, to (Y-m-d), q (invoice, customer name or phone), status, page. */
    public static function list(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($filters['from'] ?? '') !== '') {
            $where[] = 's.created_at >= :from';
            $params['from'] = $filters['from'] . ' 00:00:00';
        }
        if (($filters['to'] ?? '') !== '') {
            $where[] = 's.created_at <= :to';
            $params['to'] = $filters['to'] . ' 23:59:59';
        }
        if (in_array($filters['status'] ?? '', ['completed', 'voided'], true)) {
            $where[] = 's.status = :status';
            $params['status'] = $filters['status'];
        }
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) $filters['q']) . '%';
            $where[] = "(s.invoice_number LIKE :q1 ESCAPE '!' OR s.customer_name LIKE :q2 ESCAPE '!' OR s.customer_phone LIKE :q3 ESCAPE '!')";
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        $whereSql = implode(' AND ', $where);
        $totals = Database::fetchOne(
            "SELECT COUNT(*) AS n,
                    COALESCE(SUM(CASE WHEN s.status = 'completed' THEN s.total ELSE 0 END), 0) AS amount,
                    COALESCE(SUM(CASE WHEN s.status = 'completed' THEN 1 ELSE 0 END), 0) AS completed
               FROM shop_sales s WHERE {$whereSql}",
            $params
        ) ?? ['n' => 0, 'amount' => 0, 'completed' => 0];
        $total = (int) $totals['n'];
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, (int) ($filters['page'] ?? 1)), $pages);
        $items = Database::fetchAll(
            "SELECT s.*, u.full_name AS cashier_name,
                    (SELECT COALESCE(SUM(i.quantity), 0) FROM shop_sale_items i WHERE i.sale_id = s.id) AS units
               FROM shop_sales s LEFT JOIN users u ON u.id = s.created_by
              WHERE {$whereSql}
              ORDER BY s.created_at DESC, s.id DESC
              LIMIT :limit OFFSET :offset",
            $params + ['limit' => self::PER_PAGE, 'offset' => ($page - 1) * self::PER_PAGE]
        );
        return [
            'items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages,
            'amount' => (string) $totals['amount'], 'completed' => (int) $totals['completed'],
        ];
    }

    /** Cancel an invoice made by mistake: stock goes back, the invoice is kept and marked Cancelled. */
    public static function void(int $id, string $reason, int $adminId): ?string
    {
        $reason = mb_substr(CartService::cleanMessage(str_replace("\n", ' ', $reason)), 0, 255);
        if (mb_strlen($reason) < 3) {
            return 'Write why the invoice is cancelled (at least 3 characters).';
        }
        try {
            Database::transaction(static function () use ($id, $reason, $adminId): void {
                $sale = Database::fetchOne('SELECT id, invoice_number, status, total FROM shop_sales WHERE id = :id FOR UPDATE', ['id' => $id]);
                if ($sale === null) {
                    throw new DomainException('Invoice not found.');
                }
                if ($sale['status'] !== 'completed') {
                    throw new DomainException('This invoice is already cancelled.');
                }
                $updated = Database::execute(
                    "UPDATE shop_sales SET status = 'voided', void_reason = :r, voided_at = :now, voided_by = :by WHERE id = :id AND status = 'completed'",
                    ['r' => $reason, 'now' => date('Y-m-d H:i:s'), 'by' => $adminId, 'id' => $id]
                );
                if ($updated !== 1) {
                    throw new DomainException('This invoice is already cancelled.');
                }
                foreach (self::items($id) as $item) {
                    if ($item['product_id'] !== null) {
                        InventoryService::adjust((int) $item['product_id'], (int) $item['quantity'], 'shop_sale_voided', null, $adminId,
                            $sale['invoice_number'] . ' cancelled', $id);
                    }
                }
                ActivityLogService::log($adminId, 'shop_sale.void', 'shop_sale', $id, $sale['invoice_number'] . ' (' . money($sale['total']) . '): ' . $reason);
            });
        } catch (DomainException $e) {
            return $e->getMessage();
        }
        return null;
    }

    /** Completed in-shop sales between two dates (for the dashboard and reports). */
    public static function totals(string $from, string $to): array
    {
        try {
            $row = Database::fetchOne(
                "SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS amount FROM shop_sales
                  WHERE status = 'completed' AND created_at >= :f AND created_at <= :t",
                ['f' => $from . ' 00:00:00', 't' => $to . ' 23:59:59']
            );
        } catch (Throwable) {
            return ['count' => 0, 'amount' => '0.00']; // update script not run yet: never break the dashboard
        }
        return ['count' => (int) ($row['n'] ?? 0), 'amount' => number_format((float) ($row['amount'] ?? 0), 2, '.', '')];
    }

    /** "1,250.50" / "1250" -> 125050 cents, or null if not a valid amount (max 9,999,999.99). */
    public static function cents(string $value): ?int
    {
        $value = str_replace([',', ' '], '', trim($value));
        if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $value)) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $value), 2, '0');
        return (int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    public static function rupees(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
