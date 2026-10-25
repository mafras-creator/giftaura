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
        $waiting = CartService::takeWaiting();
        CartService::mergeSessionCart($result['id']);
        $wished = WishlistService::addPending(current_user() ?? ['id' => $result['id'], 'role' => 'customer']);
        if ($wished !== null) {
            Flash::add('success', '"' . $wished . '" was saved to your wishlist.');
        }
        EmailService::sendVerification($result['id']);
        Flash::add('success', 'Your account has been created. We sent a verification link to '
            . mb_strtolower(trim((string) $input['email'])) . ' — please open it before placing an order.');
        if ($waiting !== null) {
            Flash::add('success', '"' . $waiting . '" has been added to your cart.');
            redirect('cart.php');
        }
        redirect('account/');
    }
    $errors = $result['errors'];
    $old    = $result['old'];
}

View::render('shop/register', [
    'title'  => 'Create an account',
    'errors' => $errors,
    'old'    => $old,
    'waiting' => CartService::waiting(),
]);
