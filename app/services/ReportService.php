<?php
/**
 * GiftAura - reports from real database records.
 *
 * Definitions (same as docs/01-requirements.md, section 5.5):
 *  - Orders in range: orders whose created_at is in the range, any status.
 *  - Gross sales: SUM(orders.total_amount) for orders in range that are NOT
 *    cancelled and whose payment is NOT refunded. Summed from the orders
 *    table only (never joined to order_items), so each order counts once.
 *  - Collected revenue: SUM(payments.amount) with status "paid", by paid_at
 *    in range. (Refunded payments are excluded because their status is "refunded".)
 *  - Pending value: gross-sales orders that are still "pending".
 *  - Best sellers: quantities and line totals from non-cancelled,
 *    non-refunded orders in range.
 * Ranges include both the start and end day (Asia/Colombo time).
 */
declare(strict_types=1);

final class ReportService
{
    public const REPORTS = [
        'sales'     => 'Sales summary (by day)',
        'orders'    => 'Orders in date range',
        'status'    => 'Order status totals',
        'products'  => 'Best-selling products',
        'inventory' => 'Current inventory',
        'low_stock' => 'Low-stock products',
    ];

    private const COUNTED = "o.status <> 'cancelled' AND (p.status IS NULL OR p.status <> 'refunded')";

    /**
     * Validate a date range (Y-m-d). Defaults to the current month.
     * Returns [from, to, errorMessage|null].
     */
    public static function range(string $from, string $to): array
    {
        $valid = static fn(string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)
            && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4));
        $error = null;
        if ($from === '' || !$valid($from)) {
            if ($from !== '') { $error = 'The start date was not valid, so the start of this month is used.'; }
            $from = date('Y-m-01');
        }
        if ($to === '' || !$valid($to)) {
            if ($to !== '') { $error = 'The end date was not valid, so today is used.'; }
            $to = date('Y-m-d');
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        if ((strtotime($to) - strtotime($from)) > 366 * 86400) {
            $from = date('Y-m-d', strtotime($to . ' -366 days'));
            $error = 'The range was limited to one year.';
        }
        return [$from, $to, $error];
    }

    private static function bounds(string $from, string $to): array
    {
        return [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'];
    }

    /** Headline figures for a range. */
    public static function summary(string $from, string $to): array
    {
        [$start, $end] = self::bounds($from, $to);
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS orders_all,
                    SUM(CASE WHEN " . self::COUNTED . " THEN 1 ELSE 0 END) AS orders_counted,
                    COALESCE(SUM(CASE WHEN " . self::COUNTED . " THEN o.total_amount ELSE 0 END), 0) AS gross_sales,
                    COALESCE(SUM(CASE WHEN " . self::COUNTED . " AND o.status = 'pending' THEN o.total_amount ELSE 0 END), 0) AS pending_value,
                    SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled
               FROM orders o LEFT JOIN payments p ON p.order_id = o.id
              WHERE o.created_at >= :start AND o.created_at < :end",
            ['start' => $start, 'end' => $end]
        );
        $collected = Database::fetchValue(
            "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid' AND paid_at >= :start AND paid_at < :end",
            ['start' => $start, 'end' => $end]
        );
        $counted = (int) ($row['orders_counted'] ?? 0);
        $gross = CartService::toCents(self::money($row['gross_sales'] ?? 0));
        return [
            'orders_all'     => (int) ($row['orders_all'] ?? 0),
            'orders_counted' => $counted,
            'cancelled'      => (int) ($row['cancelled'] ?? 0),
            'gross_sales'    => self::money($row['gross_sales'] ?? 0),
            'pending_value'  => self::money($row['pending_value'] ?? 0),
            'collected'      => self::money($collected ?? 0),
            'average_order'  => $counted > 0 ? CartService::fromCents(intdiv($gross, $counted)) : '0.00',
        ];
    }

    /** Gross sales per day (days without orders are included with zero). */
    public static function salesByDay(string $from, string $to): array
    {
        [$start, $end] = self::bounds($from, $to);
        $rows = Database::fetchAll(
            "SELECT DATE(o.created_at) AS day, COUNT(*) AS orders, COALESCE(SUM(o.total_amount), 0) AS sales
               FROM orders o LEFT JOIN payments p ON p.order_id = o.id
              WHERE o.created_at >= :start AND o.created_at < :end AND " . self::COUNTED . "
              GROUP BY DATE(o.created_at)",
            ['start' => $start, 'end' => $end]
        );
        $byDay = [];
        foreach ($rows as $r) {
            $byDay[(string) $r['day']] = $r;
        }
        $result = [];
        for ($d = $from; $d <= $to; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            $result[] = [
                'day'    => $d,
                'orders' => (int) ($byDay[$d]['orders'] ?? 0),
                'sales'  => self::money($byDay[$d]['sales'] ?? 0),
            ];
        }
        return $result;
    }

    /** Every order in range (any status). */
    public static function orders(string $from, string $to): array
    {
        [$start, $end] = self::bounds($from, $to);
        return Database::fetchAll(
            'SELECT o.order_number, o.created_at, o.customer_name, o.customer_phone, o.district, o.status,
                    p.method AS payment_method, p.status AS payment_status,
                    o.subtotal, o.delivery_fee, o.discount_amount, o.total_amount
               FROM orders o LEFT JOIN payments p ON p.order_id = o.id
              WHERE o.created_at >= :start AND o.created_at < :end
              ORDER BY o.created_at ASC, o.id ASC',
            ['start' => $start, 'end' => $end]
        );
    }

    /** Count and value per current order status (includes cancelled). */
    public static function statusTotals(string $from, string $to): array
    {
        [$start, $end] = self::bounds($from, $to);
        $rows = Database::fetchAll(
            'SELECT o.status, COUNT(*) AS orders, COALESCE(SUM(o.total_amount), 0) AS value
               FROM orders o WHERE o.created_at >= :start AND o.created_at < :end GROUP BY o.status',
            ['start' => $start, 'end' => $end]
        );
        $byStatus = [];
        foreach ($rows as $r) {
            $byStatus[$r['status']] = $r;
        }
        $result = [];
        foreach (OrderService::STATUSES as $key => $label) {
            $result[] = ['status' => $key, 'label' => $label,
                         'orders' => (int) ($byStatus[$key]['orders'] ?? 0),
                         'value' => self::money($byStatus[$key]['value'] ?? 0)];
        }
        return $result;
    }

    /** Top products by quantity sold. */
    public static function bestSellers(string $from, string $to, int $limit = 10): array
    {
        [$start, $end] = self::bounds($from, $to);
        return array_map(static fn(array $r): array => ['revenue' => self::money($r['revenue'])] + $r, Database::fetchAll(
            "SELECT oi.product_id, MAX(oi.product_name) AS product_name, MAX(oi.product_sku) AS product_sku,
                    SUM(oi.quantity) AS quantity, COALESCE(SUM(oi.line_total), 0) AS revenue, COUNT(DISTINCT o.id) AS orders
               FROM order_items oi
               JOIN orders o ON o.id = oi.order_id
               LEFT JOIN payments p ON p.order_id = o.id
              WHERE o.created_at >= :start AND o.created_at < :end AND " . self::COUNTED . "
              GROUP BY oi.product_id
              ORDER BY quantity DESC, revenue DESC
              LIMIT :limit",
            ['start' => $start, 'end' => $end, 'limit' => $limit]
        ));
    }

    /** All products with stock and stock value. */
    public static function inventory(bool $lowOnly = false): array
    {
        $where = $lowOnly ? 'WHERE p.is_active = 1 AND p.stock_quantity <= p.low_stock_threshold' : '';
        $rows = Database::fetchAll(
            "SELECT p.id, p.name, p.sku, c.name AS category_name, p.price, p.stock_quantity, p.low_stock_threshold, p.is_active
               FROM products p JOIN categories c ON c.id = p.category_id
               {$where}
              ORDER BY " . ($lowOnly ? 'p.stock_quantity ASC, p.name' : 'c.name, p.name')
        );
        foreach ($rows as &$r) {
            $r['stock_value'] = CartService::fromCents(CartService::toCents(self::money($r['price'])) * (int) $r['stock_quantity']);
            $r['state'] = (int) $r['is_active'] !== 1 ? 'Inactive'
                : ((int) $r['stock_quantity'] === 0 ? 'Out of stock'
                : ((int) $r['stock_quantity'] <= (int) $r['low_stock_threshold'] ? 'Low stock' : 'In stock'));
        }
        unset($r);
        return $rows;
    }

    /**
     * Rows for CSV export: [headerRow, ...dataRows].
     */
    public static function csvRows(string $report, string $from, string $to): array
    {
        switch ($report) {
            case 'sales':
                $rows = [['Date', 'Orders counted', 'Gross sales (LKR)']];
                foreach (self::salesByDay($from, $to) as $r) {
                    $rows[] = [$r['day'], $r['orders'], $r['sales']];
                }
                $s = self::summary($from, $to);
                $rows[] = [];
                $rows[] = ['Total gross sales', $s['orders_counted'], $s['gross_sales']];
                $rows[] = ['Collected revenue (payments marked paid)', '', $s['collected']];
                $rows[] = ['Definition', 'Gross sales = orders not cancelled and payment not refunded', ''];
                return $rows;

            case 'orders':
                $rows = [['Order', 'Date', 'Customer', 'Phone', 'District', 'Status', 'Payment method', 'Payment status',
                          'Subtotal', 'Delivery', 'Discount', 'Total']];
                foreach (self::orders($from, $to) as $o) {
                    $rows[] = [$o['order_number'], $o['created_at'], $o['customer_name'], $o['customer_phone'], $o['district'],
                               OrderService::STATUSES[$o['status']] ?? $o['status'],
                               PaymentService::METHODS[$o['payment_method']] ?? '', PaymentService::STATUSES[$o['payment_status']] ?? '',
                               self::money($o['subtotal']), self::money($o['delivery_fee']), self::money($o['discount_amount']), self::money($o['total_amount'])];
                }
                return $rows;

            case 'status':
                $rows = [['Status', 'Orders', 'Value (LKR)']];
                foreach (self::statusTotals($from, $to) as $r) {
                    $rows[] = [$r['label'], $r['orders'], $r['value']];
                }
                return $rows;

            case 'products':
                $rows = [['Product', 'SKU', 'Quantity sold', 'Orders', 'Revenue (LKR)']];
                foreach (self::bestSellers($from, $to, 100) as $r) {
                    $rows[] = [$r['product_name'], $r['product_sku'], $r['quantity'], $r['orders'], $r['revenue']];
                }
                return $rows;

            case 'inventory':
            case 'low_stock':
                $rows = [['Product', 'SKU', 'Category', 'Price (LKR)', 'In stock', 'Alert level', 'Stock value (LKR)', 'Status']];
                foreach (self::inventory($report === 'low_stock') as $r) {
                    $rows[] = [$r['name'], $r['sku'], $r['category_name'], self::money($r['price']), $r['stock_quantity'],
                               $r['low_stock_threshold'], $r['stock_value'], $r['state']];
                }
                return $rows;
        }
        throw new InvalidArgumentException('Unknown report.');
    }

    /**
     * Make a value safe for spreadsheets: a cell starting with = + - @ (or tab/CR)
     * could be run as a formula by Excel ("CSV injection"), so prefix it with '.
     */
    public static function csvCell(mixed $value): string
    {
        $value = (string) ($value ?? '');
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($value)) {
            return "'" . $value;
        }
        return $value;
    }

    /** Normalise any numeric database value to a 2-decimal string. */
    private static function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
