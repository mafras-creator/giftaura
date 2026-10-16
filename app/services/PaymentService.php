<?php
/**
 * GiftAura - payment methods and payment status.
 *
 * No online payment gateway is integrated. The two methods are:
 *   cod            Cash on Delivery - an administrator records the payment
 *                  when the cash is collected.
 *   bank_transfer  Manual bank transfer - the customer submits the transfer
 *                  reference, an administrator checks the bank account and
 *                  marks it paid or failed.
 * A payment is never shown as "paid" unless an administrator recorded it.
 */
declare(strict_types=1);

final class PaymentService
{
    public const METHODS = [
        'cod'           => 'Cash on delivery',
        'bank_transfer' => 'Bank transfer',
    ];

    public const STATUSES = [
        'pending'               => 'Pending',
        'awaiting_verification' => 'Awaiting verification',
        'paid'                  => 'Paid',
        'failed'                => 'Failed',
        'refunded'              => 'Refunded',
    ];

    /** Payment methods switched on in Settings: [key => label]. */
    public static function enabledMethods(): array
    {
        $methods = [];
        if (setting('cod_enabled', '1') === '1') {
            $methods['cod'] = self::METHODS['cod'];
        }
        if (setting('bank_transfer_enabled', '1') === '1') {
            $methods['bank_transfer'] = self::METHODS['bank_transfer'];
        }
        return $methods;
    }

    /** Store bank details from Settings (empty values when not configured). */
    public static function bankDetails(): array
    {
        return [
            'bank_name'           => setting('bank_name'),
            'bank_branch'         => setting('bank_branch'),
            'bank_account_name'   => setting('bank_account_name'),
            'bank_account_number' => setting('bank_account_number'),
        ];
    }

    public static function forOrder(int $orderId): ?array
    {
        return Database::fetchOne('SELECT * FROM payments WHERE order_id = :id', ['id' => $orderId]);
    }

    /**
     * Customer submits the reference of a bank transfer for their own order.
     * Returns an error message, or null on success.
     */
    public static function submitReference(int $userId, string $orderNumber, string $reference): ?string
    {
        $reference = trim($reference);
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 \-\/#.]{2,99}$/', $reference)) {
            return 'Enter the transfer reference as shown on your bank slip (3-100 letters, numbers, spaces, - / # .).';
        }
        $order = OrderService::findForCustomer($userId, $orderNumber);
        if ($order === null) {
            return 'Order not found.';
        }
        if ($order['status'] === 'cancelled') {
            return 'This order has been cancelled.';
        }
        $payment = self::forOrder((int) $order['id']);
        if ($payment === null || $payment['method'] !== 'bank_transfer') {
            return 'This order is not paid by bank transfer.';
        }
        if (!in_array($payment['status'], ['pending', 'failed', 'awaiting_verification'], true)) {
            return 'The payment for this order has already been processed.';
        }
        Database::execute(
            "UPDATE payments SET reference = :ref, status = 'awaiting_verification'
              WHERE id = :id AND status IN ('pending', 'failed', 'awaiting_verification')",
            ['ref' => $reference, 'id' => (int) $payment['id']]
        );
        Logger::info('Bank transfer reference submitted', ['order' => $orderNumber, 'user_id' => $userId]);
        return null;
    }
}
