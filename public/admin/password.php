<?php
/**
 * GiftAura - admin panel: change your own password (super administrator and staff).
 * Staff members are sent here after logging in with a generated password.
 */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$user   = Auth::requireAdmin(null, true);
$errors = [];

if (is_post()) {
    Csrf::verify();
    $errors = UserService::changePassword($user['id'], [
        'current_password'     => $_POST['current_password'] ?? '',
        'new_password'         => $_POST['new_password'] ?? '',
        'new_password_confirm' => $_POST['new_password_confirm'] ?? '',
    ]);
    if ($errors === []) {
        Flash::add('success', 'Your password has been changed.');
        redirect('admin/');
    }
}

View::render('admin/password', [
    'title'  => 'Change password',
    'user'   => $user,
    'errors' => $errors,
    'forced' => (int) $user['must_change_password'] === 1,
]);
