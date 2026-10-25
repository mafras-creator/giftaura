<?php
/**
 * GiftAura - admin: one order. Customer contact and delivery details,
 * items, totals, status workflow and payment recording.
 * POST actions: status, payment.
 */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$admin = Auth::requireAdmin('orders');
$id = ctype_digit(query_string('id')) ? (int) query_string('id') : 0;
$order = OrderAdminService::find($id);
if ($order === null) {
    abort(404, 'Order not found.');
}

if (is_post()) {
    Csrf::verify();
    switch (post_string('action')) {
        case 'status':
            $error = OrderAdminService::changeStatus($id, post_string('status'), post_string('note'), post_string('allow_unpaid') === '1', $admin['id']);
            Flash::add($error === null ? 'success' : 'error', $error ?? 'Order status updated.');
            break;
        case 'payment':
            $error = OrderAdminService::changePaymentStatus($id, post_string('payment_status'), post_string('note'), $admin['id']);
            Flash::add($error === null ? 'success' : 'error', $error ?? 'Payment status updated.');
            break;
        default:
            abort(400, 'Unknown action.');
    }
    redirect('admin/order.php', ['id' => $id]);
}

$payment = PaymentService::forOrder($id);
View::render('admin/order', [
    'title'        => 'Order ' . $order['order_number'],
    'order'        => $order,
    'items'        => OrderService::items($id),
    'history'      => OrderService::history($id),
    'payment'      => $payment,
    'nextStatuses' => OrderService::TRANSITIONS[$order['status']] ?? [],
    'nextPayments' => OrderAdminService::PAYMENT_TRANSITIONS[$payment['status'] ?? 'pending'] ?? [],
    'recorder'     => !empty($payment['recorded_by'])
        ? Database::fetchValue('SELECT full_name FROM users WHERE id = :id', ['id' => (int) $payment['recorded_by']]) : null,
]);
