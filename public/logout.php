<?php
/**
 * GiftAura - log out. POST only, with CSRF token, so another website
 * cannot log users out with a hidden link or image.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

if (!is_post()) {
    redirect('');
}
Csrf::verify();
Auth::logout();
Flash::add('success', 'You have been logged out.');
redirect('');
