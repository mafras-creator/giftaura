<?php
/**
 * GiftAura - forgotten password: ask for a reset link by e-mail.
 * The answer is always the same, whether or not the address has an
 * account, so this page cannot be used to find out who is a customer.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

Auth::redirectIfLoggedIn();

$error = null;
$sent = false;
$email = '';

if (is_post()) {
    Csrf::verify();
    $email = mb_strtolower(post_string('email'));
    // Limit per browser session: at most 5 requests per 15 minutes.
    $recent = array_filter($_SESSION['reset_requests'] ?? [], static fn ($t): bool => is_int($t) && $t > time() - 900);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter the e-mail address of your account.';
    } elseif (count($recent) >= 5) {
        $error = 'Too many requests. Please wait 15 minutes and try again.';
    } else {
        $recent[] = time();
        $_SESSION['reset_requests'] = array_values($recent);
        EmailService::sendPasswordReset($email);
        $sent = true;
    }
}

View::render('shop/forgot-password', [
    'title' => 'Forgotten password',
    'error' => $error,
    'sent'  => $sent,
    'email' => $email,
]);
