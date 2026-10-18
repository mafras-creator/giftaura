<?php
/** GiftAura - send the verification link again (POST only, logged-in customers). */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

if (!is_post()) {
    redirect('account/');
}
Csrf::verify();
$user = Auth::requireLogin();

if ($user['email_verified_at'] !== null || $user['role'] !== 'customer') {
    Flash::add('info', 'Your e-mail address is already verified.');
} elseif (EmailService::sendVerification($user['id'])) {
    Flash::add('success', 'We sent a new verification link to ' . $user['email'] . '. It may take a minute to arrive — check the Spam folder too.');
} else {
    Flash::add('error', 'You asked for several links in the last hour. Please use the newest e-mail, or try again later.');
}
$back = $_SERVER['HTTP_REFERER'] ?? '';
$path = is_string($back) ? (string) parse_url($back, PHP_URL_PATH) : '';
header('Location: ' . safe_next($path, 'account/'), true, 303);
exit;
