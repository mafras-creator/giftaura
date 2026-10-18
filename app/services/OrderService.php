<?php
/**
 * GiftAura - orders: placing, reading, status workflow, cancellation.
 *
 * placeOrder() runs as ONE database transaction:
 *   1. lock the products in the cart (SELECT ... FOR UPDATE)
 *   2. check every product is still available and in stock
 *   3. recalculate all money from the locked database prices
 *   4. insert the order, its item snapshots, the payment record and the
 *      first status-history row
 *   5. reduce stock through InventoryService (ledger row per product)
 *   6. empty the cart
 * If anything fails, everything is rolled back: no half-created orders,
 * no lost stock, no overselling.
 */
declare(strict_types=1);

final class OrderService
{
    public const STATUSES = [
        'pending'            => 'Pending',
        'confirmed'          => 'Confirmed',
        'processing'         => 'Processing',
        'ready_for_delivery' => 'Ready for delivery',
        'delivered'          => 'Delivered',
        'cancelled'          => 'Cancelled',
    ];

    /** Allowed status changes (see docs/02-architecture.md, section 5). */
    public const TRANSITIONS = [
        'pending'            => ['confirmed', 'cancelled'],
        'confirmed'          => ['processing', 'cancelled'],
        'processing'         => ['ready_for_delivery', 'cancelled'],
        'ready_for_delivery' => ['delivered'],
        'delivered'          => [],
        'cancelled'          => [],
    ];

    /** The happy-path sequence, used to draw the progress tracker. */
    public const PROGRESS = ['pending', 'confirmed', 'processing', 'ready_for_delivery', 'delivered'];

    private const NUMBER_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O, 1/I

    // -----------------------------------------------------------------
    // Placing an order
    // -----------------------------------------------------------------

    /**
     * Validate checkout input and place the order.
     *
     * $input keys: customer_name, customer_phone, address_id (saved address)
     * OR recipient_name, phone, address_line1, address_line2, city, district,
     * postal_code (new address), save_address ('1'), delivery_notes,
     * payment_method, cart_fingerprint.
     *
     * Returns ['order_number' => string] on success or
     *         ['errors' => [field => message], 'general' => ?string] on failure.
     */
    public static function placeOrder(array $user, array $input): array
    {
        $userId = (int) $user['id'];
        $errors = [];

        // Defence in depth: the checkout page already blocks unverified accounts.
        if (Database::fetchValue('SELECT email_verified_at FROM users WHERE id = :id', ['id' => $userId]) === null) {
            return ['errors' => [], 'general' => 'Please verify your e-mail address before placing an order.'];
        }

        // Contact details
        $v = new Validator($input, ['customer_name' => 'Your name', 'customer_phone' => 'Your phone number']);
        $v->rules([
            'customer_name'  => ['required', 'min:2', 'max:100'],
            'customer_phone' => ['required', 'phone'],
            'delivery_notes' => ['max:500'],
        ]);
        $errors += $v->errors();

        // Payment method (only methods switched on in Settings)
        $method = is_string($input['payment_method'] ?? null) ? $input['payment_method'] : '';
        if (!array_key_exists($method, PaymentService::enabledMethods())) {
            $errors['payment_method'] = 'Choose a payment method.';
        }

        // Delivery address: a saved one (must belong to this customer) or a new one
        $addressId = ctype_digit((string) ($input['address_id'] ?? '')) ? (int) $input['address_id'] : 0;
        $newAddress = null;
        if ($addressId > 0) {
            $saved = AddressService::find($userId, $addressId);
            if ($saved === null) {
                $errors['address_id'] = 'Choose one of your saved addresses.';
            } else {
                $address = $saved;
            }
        } else {
            [$addressErrors, $newAddress] = AddressService::validate($input);
            $errors += $addressErrors;
            $address = $newAddress;
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'general' => 'Please correct the highlighted fields.'];
        }

        $submittedFingerprint = is_string($input['cart_fingerprint'] ?? null) ? $input['cart_fingerprint'] : '';

        try {
            $orderNumber = Database::transaction(static function () use (
                $user, $userId, $v, $method, $address, $submittedFingerprint
            ): string {
                // 1. Cart lines (read inside the transaction)
                $cartRows = Database::fetchAll(
                    'SELECT ci.product_id, ci.quantity, ci.customization_text
                       FROM cart_items ci JOIN carts c ON c.id = ci.cart_id
                      WHERE c.user_id = :uid ORDER BY ci.added_at, ci.id',
                    ['uid' => $userId]
                );
                if ($cartRows === []) {
                    throw new CheckoutException('Your cart is empty.');
                }

                // 2. Lock the products so no other order can change their stock meanwhile
                $params = [];
                $in = [];
                foreach ($cartRows as $i => $row) {
                    $in[] = ':p' . $i;
                    $params['p' . $i] = (int) $row['product_id'];
                }
                $locked = Database::fetchAll(
                    'SELECT id, name, sku, price, stock_quantity, is_active, category_id,
                            is_customizable, custom_max_length, custom_required
                       FROM products WHERE id IN (' . implode(', ', $in) . ') ORDER BY id FOR UPDATE',
                    $params
                );
                $products = [];
                foreach ($locked as $p) {
                    $products[(int) $p['id']] = $p;
                }

                // 3. Check availability and recalculate money from database prices
                $visible = CategoryService::visibleIds();
                $lines = [];
                $subtotalCents = 0;
                $fingerprintSource = [];
                foreach ($cartRows as $row) {
                    $pid = (int) $row['product_id'];
                    $qty = (int) $row['quantity'];
                    $p = $products[$pid] ?? null;
                    if ($p === null || (int) $p['is_active'] !== 1 || !in_array((int) $p['category_id'], $visible, true)) {
                        throw new CheckoutException('An item in your cart is no longer available. Please review your cart.');
                    }
                    if ((int) $p['stock_quantity'] < $qty) {
                        throw new CheckoutException(
                            'Sorry, only ' . max(0, (int) $p['stock_quantity']) . ' of "' . $p['name'] . '" left in stock. Please update your cart.'
                        );
                    }
                    // Personalisation: re-checked here, because the product settings may have changed.
                    $message = '';
                    if ((int) $p['is_customizable'] === 1) {
                        $message = (string) ($row['customization_text'] ?? '');
                        if ($message === '' && (int) $p['custom_required'] === 1) {
                            throw new CheckoutException('Please add your personalisation message for "' . $p['name'] . '" in your cart.');
                        }
                        if (mb_strlen($message) > (int) $p['custom_max_length']) {
                            throw new CheckoutException('Your personalisation message for "' . $p['name'] . '" is too long. Please shorten it in your cart.');
                        }
                    }
                    $priceCents = CartService::toCents((string) $p['price']);
                    $lineCents = $priceCents * $qty;
                    $subtotalCents += $lineCents;
                    $lines[] = ['product' => $p, 'quantity' => $qty, 'price' => CartService::fromCents($priceCents),
                                'line_total' => CartService::fromCents($lineCents), 'message' => $message];
                    $fingerprintSource[] = CartService::fingerprintPart($pid, $qty, CartService::fromCents($priceCents), $message);
                }
                $deliveryCents = CartService::deliveryFeeCents($subtotalCents);
                $totalCents = $subtotalCents + $deliveryCents;

                // The customer must have seen exactly these items, quantities, prices and delivery fee.
                $fingerprint = hash('sha256', implode('|', $fingerprintSource) . '|' . $deliveryCents);
                if (!hash_equals($fingerprint, $submittedFingerprint)) {
                    throw new CheckoutException('Your cart or its prices changed while you were checking out. Please review the updated total and place the order again.');
                }

                // 4. Order header with contact and delivery snapshot
                $orderNumber = self::newOrderNumber();
                $orderId = Database::insert(
                    'INSERT INTO orders
                        (order_number, user_id, status, customer_name, customer_email, customer_phone,
                         recipient_name, recipient_phone, address_line1, address_line2, city, district, postal_code,
                         delivery_notes, subtotal, delivery_fee, discount_amount, total_amount)
                     VALUES
                        (:num, :uid, \'pending\', :cname, :cemail, :cphone,
                         :rname, :rphone, :l1, :l2, :city, :district, :postal,
                         :notes, :subtotal, :fee, :discount, :total)',
                    [
                        'num' => $orderNumber, 'uid' => $userId,
                        'cname' => $v->value('customer_name'), 'cemail' => $user['email'], 'cphone' => $v->value('customer_phone'),
                        'rname' => $address['recipient_name'], 'rphone' => $address['phone'],
                        'l1' => $address['address_line1'],
                        'l2' => ($address['address_line2'] ?? '') !== '' ? $address['address_line2'] : null,
                        'city' => $address['city'], 'district' => $address['district'],
                        'postal' => ($address['postal_code'] ?? '') !== '' ? $address['postal_code'] : null,
                        'notes' => $v->value('delivery_notes') !== '' ? $v->value('delivery_notes') : null,
                        'subtotal' => CartService::fromCents($subtotalCents),
                        'fee' => CartService::fromCents($deliveryCents),
                        'discount' => '0.00',
                        'total' => CartService::fromCents($totalCents),
                    ]
                );

                // 5. Item snapshots + stock reduction (with ledger rows)
                foreach ($lines as $line) {
                    Database::execute(
                        'INSERT INTO order_items (order_id, product_id, product_name, product_sku, unit_price, quantity, line_total, customization_text)
                         VALUES (:oid, :pid, :name, :sku, :price, :qty, :line, :custom)',
                        [
                            'oid' => $orderId, 'pid' => (int) $line['product']['id'], 'name' => $line['product']['name'],
                            'sku' => $line['product']['sku'], 'price' => $line['price'], 'qty' => $line['quantity'],
                            'line' => $line['line_total'], 'custom' => $line['message'] !== '' ? $line['message'] : null,
                        ]
                    );
                    InventoryService::adjust((int) $line['product']['id'], -$line['quantity'], 'order_placed', $orderId, $userId,
                        'Order ' . $orderNumber);
                }

                // 6. Payment record (never "paid" at this point)
                Database::execute(
                    "INSERT INTO payments (order_id, method, status, amount) VALUES (:oid, :method, 'pending', :amount)",
                    ['oid' => $orderId, 'method' => $method, 'amount' => CartService::fromCents($totalCents)]
                );

                // 7. First status-history entry
                Database::execute(
                    "INSERT INTO order_status_history (order_id, from_status, to_status, changed_by, note)
                     VALUES (:oid, NULL, 'pending', :uid, 'Order placed by customer')",
                    ['oid' => $orderId, 'uid' => $userId]
                );

                // 8. Empty the cart
                CartService::clearForUser($userId);

                return $orderNumber;
            });
        } catch (CheckoutException $e) {
            return ['errors' => [], 'general' => $e->getMessage()];
        }

        // Save a new address to the address book if asked (after the order is safely stored).
        if ($newAddress !== null && ($input['save_address'] ?? '') === '1') {
            try {
                AddressService::create($userId, $newAddress + ['label' => 'Home']);
            } catch (Throwable $e) {
                Logger::warning('Could not save address after order', ['order' => $orderNumber]);
            }
        }

        Logger::info('Order placed', ['order' => $orderNumber, 'user_id' => $userId]);
        EmailService::orderPlaced($orderNumber);
        return ['order_number' => $orderNumber];
    }

    /** Unique, hard-to-guess order reference such as GA261016-7K3M9Q. */
    private static function newOrderNumber(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::NUMBER_ALPHABET[random_int(0, strlen(self::NUMBER_ALPHABET) - 1)];
            }
            $number = 'GA' . date('ymd') . '-' . $code;
            if (Database::fetchValue('SELECT 1 FROM orders WHERE order_number = :n', ['n' => $number]) === null) {
                return $number;
            }
        }
        throw new RuntimeException('Could not generate a unique order number.');
    }

    // -----------------------------------------------------------------
    // Reading orders (customer side: always filtered by user_id)
    // -----------------------------------------------------------------

    /** A customer's orders, newest first, with payment info. */
    public static function forCustomer(int $userId, int $page = 1, int $perPage = 10): array
    {
        $total = (int) Database::fetchValue('SELECT COUNT(*) FROM orders WHERE user_id = :uid', ['uid' => $userId]);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $items = Database::fetchAll(
            'SELECT o.id, o.order_number, o.status, o.total_amount, o.created_at,
                    p.method AS payment_method, p.status AS payment_status,
                    (SELECT SUM(oi.quantity) FROM order_items oi WHERE oi.order_id = o.id) AS units
               FROM orders o
               LEFT JOIN payments p ON p.order_id = o.id
              WHERE o.user_id = :uid
              ORDER BY o.created_at DESC, o.id DESC
              LIMIT :limit OFFSET :offset',
            ['uid' => $userId, 'limit' => $perPage, 'offset' => ($page - 1) * $perPage]
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** One order by its number, only if it belongs to the customer. */
    public static function findForCustomer(int $userId, string $orderNumber): ?array
    {
        if (!self::isOrderNumber($orderNumber)) {
            return null;
        }
        return Database::fetchOne(
            'SELECT * FROM orders WHERE order_number = :num AND user_id = :uid',
            ['num' => $orderNumber, 'uid' => $userId]
        );
    }

    /** For the public tracking page: order number AND the email used for the order must match. */
    public static function findForTracking(string $orderNumber, string $email): ?array
    {
        if (!self::isOrderNumber($orderNumber) || $email === '') {
            return null;
        }
        return Database::fetchOne(
            'SELECT * FROM orders WHERE order_number = :num AND customer_email = :email',
            ['num' => $orderNumber, 'email' => mb_strtolower($email)]
        );
    }

    public static function items(int $orderId): array
    {
        return Database::fetchAll(
            'SELECT oi.*, p.slug AS product_slug, p.is_active AS product_active
               FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id
              WHERE oi.order_id = :id ORDER BY oi.id',
            ['id' => $orderId]
        );
    }

    public static function history(int $orderId): array
    {
        return Database::fetchAll(
            'SELECT h.from_status, h.to_status, h.note, h.created_at, u.role AS changed_by_role
               FROM order_status_history h LEFT JOIN users u ON u.id = h.changed_by
              WHERE h.order_id = :id ORDER BY h.created_at ASC, h.id ASC',
            ['id' => $orderId]
        );
    }

    public static function isOrderNumber(string $value): bool
    {
        return (bool) preg_match('/^GA\d{6}-[A-Z2-9]{6}$/', $value);
    }

    // -----------------------------------------------------------------
    // Status workflow
    // -----------------------------------------------------------------

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Move an order to a new status along an allowed transition.
     * Cancelling returns every item's quantity to stock (once).
     * $onlyFrom: if given, the order must still be in exactly this status
     * when it is locked (protects against two people changing it at once).
     * Returns an error message, or null on success.
     */
    public static function changeStatus(int $orderId, string $newStatus, ?int $changedBy, ?string $note = null, ?string $onlyFrom = null): ?string
    {
        if (!array_key_exists($newStatus, self::STATUSES)) {
            return 'Unknown order status.';
        }
        try {
            Database::transaction(static function () use ($orderId, $newStatus, $changedBy, $note, $onlyFrom): void {
                $order = Database::fetchOne('SELECT id, order_number, status FROM orders WHERE id = :id FOR UPDATE', ['id' => $orderId]);
                if ($order === null) {
                    throw new CheckoutException('Order not found.');
                }
                if ($onlyFrom !== null && $order['status'] !== $onlyFrom) {
                    throw new CheckoutException('This order has just been updated by the store and can no longer be changed here. Please refresh the page.');
                }
                if (!self::canTransition($order['status'], $newStatus)) {
                    throw new CheckoutException(sprintf(
                        'An order that is "%s" cannot be changed to "%s".',
                        self::STATUSES[$order['status']],
                        self::STATUSES[$newStatus]
                    ));
                }

                if ($newStatus === 'cancelled') {
                    foreach (self::items($orderId) as $item) {
                        if ($item['product_id'] !== null) {
                            InventoryService::adjust((int) $item['product_id'], (int) $item['quantity'], 'order_cancelled',
                                $orderId, $changedBy, 'Order ' . $order['order_number'] . ' cancelled');
                        }
                    }
                }

                Database::execute('UPDATE orders SET status = :status WHERE id = :id', ['status' => $newStatus, 'id' => $orderId]);
                Database::execute(
                    'INSERT INTO order_status_history (order_id, from_status, to_status, changed_by, note)
                     VALUES (:oid, :from, :to, :by, :note)',
                    [
                        'oid' => $orderId, 'from' => $order['status'], 'to' => $newStatus, 'by' => $changedBy,
                        'note' => $note !== null && $note !== '' ? mb_substr($note, 0, 500) : null,
                    ]
                );
            });
        } catch (CheckoutException $e) {
            return $e->getMessage();
        }
        return null;
    }

    /** Customers may cancel their own order only while it is still pending. */
    public static function cancelByCustomer(int $userId, string $orderNumber, string $reason): ?string
    {
        $order = self::findForCustomer($userId, $orderNumber);
        if ($order === null) {
            return 'Order not found.';
        }
        if ($order['status'] !== 'pending') {
            return 'This order can no longer be cancelled online because the store has already started working on it. Please contact the store.';
        }
        $reason = trim(mb_substr($reason, 0, 300));
        $note = 'Cancelled by customer' . ($reason !== '' ? ': ' . $reason : '');
        $error = self::changeStatus((int) $order['id'], 'cancelled', $userId, $note, 'pending');
        if ($error === null) {
            EmailService::orderStatusChanged((int) $order['id'], 'cancelled', $note);
        }
        return $error;
    }
}
