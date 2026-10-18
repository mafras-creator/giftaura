<?php
/** GiftAura - choose a new password with the link from the e-mail. */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$token = is_post() ? post_string('token') : query_string('token');
$valid = TokenService::peek($token, TokenService::RESET) !== null;
$errors = [];

if (is_post()) {
    Csrf::verify();
    $errors = UserService::resetPassword($token, [
        'new_password'         => $_POST['new_password'] ?? '',
        'new_password_confirm' => $_POST['new_password_confirm'] ?? '',
    ]);
    if ($errors === []) {
        if (Auth::check()) {
            Auth::logout();
        }
        Flash::add('success', 'Your password has been changed. Please log in with your new password.');
        redirect('login.php');
    }
    if (isset($errors['token'])) {
        $valid = false;
    }
}

View::render('shop/reset-password', [
    'title'  => 'Choose a new password',
    'token'  => $token,
    'valid'  => $valid,
    'errors' => $errors,
    'hideVerifyBanner' => true,
]);
