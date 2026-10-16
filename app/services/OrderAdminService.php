<?php
/**
 * GiftAura - order and payment management for administrators.
 * Status changes use OrderService::changeStatus() (allowed transitions only,
 * history row, stock restored on cancellation). Payment changes follow the
 * rules in PAYMENT_TRANSITIONS and record who made them and when.
 */
declare(strict_types=1);

final class OrderAdminService
{
    public const PER_PAGE = 20;

    /** Allowed payment status changes made by an administrator. */
    public const PAYMENT_TRANSITIONS = [
        'pending'               => ['paid', 'failed'],
        'awaiting_verification' => ['paid', 'failed'],
        'failed'                => ['paid'],
        'paid'                  => ['refunded'],
        'refunded'              => [],
    ];

    /**
     * Filters: q (order number, customer name, email or phone), status,
     * payment (payment status), method, from, to (Y-m-d), page.
     */
    public static function list(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']) . '%';
            $where[] = "(o.order_number LIKE :q1 ESCAPE '!' OR o.customer_name LIKE :q2 ESCAPE '!'
                         OR o.customer_email LIKE :q3 ESCAPE '!' OR o.customer_phone LIKE :q4 ESCAPE '!')";
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        if (isset(OrderService::STATUSES[$filters['status'] ?? ''])) {
            $where[] = 'o.status = :status';
            $params['status'] = $filters['status'];
        }
        if (isset(PaymentService::STATUSES[$filters['payment'] ?? ''])) {
            $where[] = 'p.status = :pstatus';
            $params['pstatus'] = $filters['payment'];
        }
        if (isset(PaymentService::METHODS[$filters['method'] ?? ''])) {
            $where[] = 'p.method = :method';
            $params['method'] = $filters['method'];
        }
        if (($filters['from'] ?? '') !== '') {
            $where[] = 'o.created_at >= :from';
            $params['from'] = $filters['from'] . ' 00:00:00';
        }
        if (($filters['to'] ?? '') !== '') {
            $where[] = 'o.created_at < :to';
            $params['to'] = date('Y-m-d', strtotime($filters['to'] . ' +1 day')) . ' 00:00:00';
        }
        $whereSql = implode(' AND ', $where);
        $from = 'FROM orders o LEFT JOIN payments p ON p.order_id = o.id';

        $total = (int) Database::fetchValue("SELECT COUNT(*) {$from} WHERE {$whereSql}", $params);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, (int) ($filters['page'] ?? 1)), $pages);
        $items = Database::fetchAll(
            "SELECT o.id, o.order_number, o.status, o.customer_name, o.customer_phone, o.district, o.total_amount, o.created_at,
                    p.method AS payment_method, p.status AS payment_status,
                    (SELECT SUM(oi.quantity) FROM order_items oi WHERE oi.order_id = o.id) AS units
               {$from}
              WHERE {$whereSql}
              ORDER BY o.created_at DESC, o.id DESC
              LIMIT :limit OFFSET :offset",
            $params + ['limit' => self::PER_PAGE, 'offset' => ($page - 1) * self::PER_PAGE]
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public static function find(int $id): ?array
    {
        return Database::fetchOne(
            'SELECT o.*, u.full_name AS account_name, u.email AS account_email, u.is_active AS account_active
               FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = :id',
            ['id' => $id]
        );
    }

    /**
     * Change the order status. Confirming a bank-transfer order whose payment
     * is not yet "paid" requires $allowUnpaid (the admin ticks a box), so a
     * transfer is not shipped by mistake before the money arrives.
     */
    public static function changeStatus(int $orderId, string $newStatus, string $note, bool $allowUnpaid, int $adminId): ?string
    {
        $order = self::find($orderId);
        if ($order === null) {
            return 'Order not found.';
        }
        $payment = PaymentService::forOrder($orderId);
        if ($newStatus === 'confirmed' && ($payment['method'] ?? '') === 'bank_transfer'
            && ($payment['status'] ?? '') !== 'paid' && !$allowUnpaid) {
            return 'This order is paid by bank transfer and the payment is not marked as paid yet. '
                 . 'Verify the transfer first, or tick "Confirm before payment" if you have agreed this with the customer.';
        }
        $error = OrderService::changeStatus($orderId, $newStatus, $adminId, $note, $order['status']);
        if ($error === null) {
            ActivityLogService::log($adminId, 'order.status', 'order', $orderId,
                $order['order_number'] . ': ' . $order['status'] . ' -> ' . $newStatus . ($note !== '' ? ' (' . $note . ')' : ''));
        }
        return $error;
    }

    /** Record a payment status change. Returns an error message or null. */
    public static function changePaymentStatus(int $orderId, string $newStatus, string $note, int $adminId): ?string
    {
        if (!isset(PaymentService::STATUSES[$newStatus])) {
            return 'Unknown payment status.';
        }
        $note = trim(mb_substr($note, 0, 500));
        try {
            Database::transaction(static function () use ($orderId, $newStatus, $note, $adminId): void {
                $payment = Database::fetchOne('SELECT * FROM payments WHERE order_id = :id FOR UPDATE', ['id' => $orderId]);
                $order = Database::fetchOne('SELECT id, order_number, status FROM orders WHERE id = :id', ['id' => $orderId]);
                if ($payment === null || $order === null) {
                    throw new CheckoutException('Order not found.');
                }
                if (!in_array($newStatus, self::PAYMENT_TRANSITIONS[$payment['status']] ?? [], true)) {
                    throw new CheckoutException(sprintf(
                        'A payment that is "%s" cannot be changed to "%s".',
                        PaymentService::STATUSES[$payment['status']], PaymentService::STATUSES[$newStatus]
                    ));
                }
                if ($newStatus === 'paid' && $order['status'] === 'cancelled') {
                    throw new CheckoutException('This order is cancelled, so it cannot be marked as paid.');
                }
                if ($newStatus === 'refunded' && $note === '') {
                    throw new CheckoutException('Please add a note explaining the refund (amount, method, date).');
                }
                Database::execute(
                    'UPDATE payments
                        SET status = :status,
                            paid_at = CASE WHEN :paid = 1 THEN :now1 ELSE paid_at END,
                            refunded_at = CASE WHEN :refunded = 1 THEN :now2 ELSE refunded_at END,
                            recorded_by = :admin,
                            admin_note = :note
                      WHERE id = :id',
                    [
                        'status' => $newStatus, 'paid' => $newStatus === 'paid' ? 1 : 0, 'now1' => date('Y-m-d H:i:s'),
                        'refunded' => $newStatus === 'refunded' ? 1 : 0, 'now2' => date('Y-m-d H:i:s'),
                        'admin' => $adminId, 'note' => $note !== '' ? $note : $payment['admin_note'], 'id' => (int) $payment['id'],
                    ]
                );
                ActivityLogService::log($adminId, 'payment.' . $newStatus, 'order', $orderId,
                    $order['order_number'] . ': ' . $payment['status'] . ' -> ' . $newStatus . ($note !== '' ? ' (' . $note . ')' : ''));
            });
        } catch (CheckoutException $e) {
            return $e->getMessage();
        }
        return null;
    }

    /** Counts by status, for the filter tabs. */
    public static function statusCounts(): array
    {
        $counts = array_fill_keys(array_keys(OrderService::STATUSES), 0);
        foreach (Database::fetchAll('SELECT status, COUNT(*) AS n FROM orders GROUP BY status') as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }
        return $counts;
    }
}
