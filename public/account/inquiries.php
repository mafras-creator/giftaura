<?php
/** GiftAura - My account: messages I sent from the Contact page and the store's replies. */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$user = Auth::requireLogin();

View::render('shop/account/inquiries', [
    'title' => 'My inquiries',
    'user'  => $user,
    'items' => $user['role'] === 'customer' ? InquiryService::forUser($user['id']) : [],
]);
