<?php
/**
 * GiftAura - log in (customers and administrators).
 * GET: show the form. POST: check CSRF token, rate limit and password.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

Auth::redirectIfLoggedIn();

$error = null;
$old   = [];
$next  = query_string('next');

if (is_post()) {
    Csrf::verify();
    $email    = mb_strtolower(post_string('email'));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $next     = post_string('next');
    $old      = ['email' => $email];

    if ($email === '' || $password === '') {
        $error = 'Enter your email (or staff username) and password.';
    } else {
        $result = Auth::attempt($email, $password);
        if ($result === Auth::OK) {
            $user = current_user();
            $waiting = CartService::takeWaiting();
            CartService::mergeSessionCart($user['id']);
            Flash::add('success', 'Welcome back, ' . $user['full_name'] . '.');
            $back = CartService::takeReturn();
            if ($waiting !== null && $user['role'] === 'customer') {
                Flash::add('success', '"' . $waiting . '" has been added to your cart.');
                if ($next === '') {
                    $next = $back; // back to the page they were on, not to the cart
                }
            }
            $wished = WishlistService::addPending($user);
            if ($wished !== null) {
                Flash::add('success', '"' . $wished . '" was saved to your wishlist.');
            }
            $fallback = Auth::isPanelRole($user['role']) ? 'admin/' : 'account/';
            header('Location: ' . safe_next($next, $fallback), true, 303);
            exit;
        }
        $error = $result === Auth::BLOCKED
            ? 'Too many unsuccessful login attempts. Please wait 15 minutes and try again.'
            : 'The email/username or password is incorrect.';
    }
}

View::render('shop/login', [
    'title' => 'Log in',
    'error' => $error,
    'old'   => $old,
    'next'  => $next,
    'waiting' => CartService::waiting(),
]);
