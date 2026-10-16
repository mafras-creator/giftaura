<?php
/** GiftAura - My account: change password. */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$user   = Auth::requireLogin();
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
        redirect('account/password.php');
    }
}

View::render('shop/account/password', [
    'title'  => 'Change password',
    'user'   => $user,
    'errors' => $errors,
]);
