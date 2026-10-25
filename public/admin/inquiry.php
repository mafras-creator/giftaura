<?php
/**
 * GiftAura - admin: one customer inquiry.
 * POST actions: reply (message + status in_progress|resolved), status (new|in_progress|resolved).
 */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$admin = Auth::requireAdmin('inquiries');

$id = ctype_digit(query_string('id')) ? (int) query_string('id') : 0;
$inquiry = InquiryService::find($id);
if ($inquiry === null) {
    abort(404, 'Inquiry not found.');
}

$error = null;
$draft = '';
if (is_post()) {
    Csrf::verify();
    $action = post_string('action');
    if ($action === 'reply') {
        $draft = mb_substr(post_string('message'), 0, InquiryService::REPLY_MAX + 500);
        $error = InquiryService::reply($id, $admin['id'], $draft, post_string('status'));
        if ($error === null) {
            Flash::add('success', 'Your reply was saved and e-mailed to ' . $inquiry['email'] . '.');
            redirect('admin/inquiry.php', ['id' => $id]);
        }
    } elseif ($action === 'status') {
        $ok = InquiryService::setStatus($id, post_string('status'), $admin['id']);
        Flash::add($ok ? 'success' : 'error', $ok ? 'Status updated.' : 'Unknown status.');
        redirect('admin/inquiry.php', ['id' => $id]);
    } else {
        abort(400, 'Unknown action.');
    }
}

$customer = null;
if ($inquiry['user_id'] !== null) {
    $customer = Database::fetchOne('SELECT id, full_name, email FROM users WHERE id = :id', ['id' => $inquiry['user_id']]);
}
$order = null;
if ($inquiry['order_number'] !== null && Auth::can('orders')) {
    $order = Database::fetchOne('SELECT id, order_number, status FROM orders WHERE order_number = :n', ['n' => $inquiry['order_number']]);
}

View::render('admin/inquiry', [
    'title'    => 'Inquiry ' . InquiryService::reference($id),
    'inquiry'  => $inquiry,
    'replies'  => InquiryService::replies($id),
    'customer' => $customer,
    'order'    => $order,
    'error'    => $error,
    'draft'    => $draft,
]);
