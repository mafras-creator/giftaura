<?php
/** GiftAura - My account: view and edit profile (name, phone). */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$user   = Auth::requireLogin();
$errors = [];
$old    = [];

if (is_post()) {
    Csrf::verify();
    $input  = ['full_name' => $_POST['full_name'] ?? '', 'phone' => $_POST['phone'] ?? ''];
    $errors = UserService::updateProfile($user['id'], $input);
    if ($errors === []) {
        Flash::add('success', 'Your profile has been updated.');
        redirect('account/');
    }
    $old = ['full_name' => post_string('full_name'), 'phone' => post_string('phone')];
}

View::render('shop/account/profile', [
    'title'  => 'My account',
    'user'   => $user,
    'errors' => $errors,
    'old'    => $old,
]);
