<?php
/**
 * GiftAura - admin: one customer (contact details, addresses, orders).
 * POST actions: activate, deactivate, temp_password, mark_verified, resend_verification.
 */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$admin = Auth::requireAdmin('customers');
$id = ctype_digit(query_string('id')) ? (int) query_string('id') : 0;
$customer = CustomerAdminService::find($id);
if ($customer === null) {
    abort(404, 'Customer not found.');
}
$tempPassword = null;

if (is_post()) {
    Csrf::verify();
    switch (post_string('action')) {
        case 'activate':
        case 'deactivate':
            CustomerAdminService::setActive($id, post_string('action') === 'activate', $admin['id']);
            Flash::add('success', post_string('action') === 'activate' ? 'Account activated.' : 'Account deactivated. The customer can no longer log in.');
            redirect('admin/customer.php', ['id' => $id]);
        case 'temp_password':
            // Shown once on this response only; never stored in the session or logs.
            $tempPassword = CustomerAdminService::setTemporaryPassword($id, $admin['id']);
            break;
        case 'mark_verified':
            if (UserService::markEmailVerified($id)) {
                ActivityLogService::log($admin['id'], 'customer.email_verified', 'customer', $id,
                    'E-mail ' . $customer['email'] . ' marked as verified by an administrator');
                Flash::add('success', 'The e-mail address is now marked as verified.');
            }
            redirect('admin/customer.php', ['id' => $id]);
        case 'resend_verification':
            if ($customer['email_verified_at'] !== null) {
                Flash::add('info', 'This e-mail address is already verified.');
            } elseif (EmailService::sendVerification($id)) {
                Flash::add('success', 'A new verification link was sent to ' . $customer['email'] . '. See the E-mail log for the result.');
            } else {
                Flash::add('error', 'Three links were already sent in the last hour. Please wait before sending another.');
            }
            redirect('admin/customer.php', ['id' => $id]);
        default:
            abort(400, 'Unknown action.');
    }
}

View::render('admin/customer', [
    'title'        => $customer['full_name'],
    'customer'     => $customer,
    'orders'       => CustomerAdminService::orders($id),
    'addresses'    => CustomerAdminService::addresses($id),
    'tempPassword' => $tempPassword,
]);
