<?php
/**
 * GiftAura - confirm an e-mail address from the link in the e-mail.
 *
 * GET  shows a "Confirm" button; only the POST (with CSRF token) uses the
 * link. E-mail security scanners often open links automatically, and this
 * way such a scan cannot use up the link by itself.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$token = is_post() ? post_string('token') : query_string('token');
$state = TokenService::peek($token, TokenService::VERIFY) !== null ? 'confirm' : 'invalid';

if (is_post()) {
    Csrf::verify();
    if (UserService::verifyEmail($token)) {
        Flash::add('success', 'Thank you — your e-mail address is verified. You can now place orders.');
        redirect(Auth::check() ? 'cart.php' : 'login.php');
    }
    $state = 'invalid';
}

View::render('shop/verify-email', [
    'title' => 'Verify your e-mail',
    'token' => $token,
    'state' => $state,
    'hideVerifyBanner' => true,
]);
