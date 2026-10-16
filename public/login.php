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
        $error = 'Enter your email and password.';
    } else {
        $result = Auth::attempt($email, $password);
        if ($result === Auth::OK) {
            $user = current_user();
            Flash::add('success', 'Welcome back, ' . $user['full_name'] . '.');
            $fallback = $user['role'] === 'admin' ? 'admin/' : 'account/';
            header('Location: ' . safe_next($next, $fallback), true, 303);
            exit;
        }
        $error = $result === Auth::BLOCKED
            ? 'Too many unsuccessful login attempts. Please wait 15 minutes and try again.'
            : 'The email or password is incorrect.';
    }
}

View::render('shop/login', [
    'title' => 'Log in',
    'error' => $error,
    'old'   => $old,
    'next'  => $next,
]);
