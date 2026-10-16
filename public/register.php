<?php
/**
 * GiftAura - customer registration.
 * Creates a customer account (never an administrator) and logs the user in.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

Auth::redirectIfLoggedIn();

$errors = [];
$old    = [];

if (is_post()) {
    Csrf::verify();
    // Only the expected fields are passed on; anything else in the form
    // (for example a forged "role" field) is ignored.
    $input = [
        'full_name'        => $_POST['full_name'] ?? '',
        'email'            => $_POST['email'] ?? '',
        'phone'            => $_POST['phone'] ?? '',
        'password'         => $_POST['password'] ?? '',
        'password_confirm' => $_POST['password_confirm'] ?? '',
    ];
    $result = UserService::registerCustomer($input);

    if (isset($result['id'])) {
        Auth::loginById($result['id']);
        CartService::mergeSessionCart($result['id']);
        Flash::add('success', 'Your account has been created. Welcome to GiftAura!');
        redirect('account/');
    }
    $errors = $result['errors'];
    $old    = $result['old'];
}

View::render('shop/register', [
    'title'  => 'Create an account',
    'errors' => $errors,
    'old'    => $old,
]);
