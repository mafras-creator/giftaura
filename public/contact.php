<?php
/**
 * GiftAura - contact and store information, and the contact form.
 * Store details come from the settings table; empty settings are hidden.
 * POST: send a message (saved as a customer inquiry, answered in Admin -> Inquiries).
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$user = current_user();
$errors = [];
$old = $user !== null && $user['role'] === 'customer'
    ? ['name' => $user['full_name'], 'email' => (string) $user['email'], 'phone' => (string) ($user['phone'] ?? '')]
    : [];
$prefillOrder = query_string('order');
if ($prefillOrder !== '' && OrderService::isOrderNumber($prefillOrder)) {
    $old['order_number'] = $prefillOrder;
    $old['subject'] = 'order';
}

if (is_post()) {
    Csrf::verify();
    $input = [];
    foreach (['name', 'email', 'phone', 'subject', 'order_number', 'website'] as $field) {
        $input[$field] = mb_substr(post_string($field), 0, 200);
    }
    $input['message'] = mb_substr(post_string('message'), 0, 5000);
    $result = InquiryService::submit($input, $user);
    if (isset($result['id'])) {
        Flash::add('success', 'Thank you! Your message was sent' . ($result['id'] > 0 ? ' (reference ' . InquiryService::reference($result['id']) . ')' : '')
            . '. We will reply to ' . mb_strtolower(trim($input['email'])) . ' as soon as possible.');
        redirect('contact.php');
    }
    $errors = $result['errors'];
    $old = $result['old'];
}

View::render('shop/contact', [
    'title'    => 'Contact us',
    'phone'    => setting('store_phone'),
    'whatsapp' => setting('store_whatsapp'),
    'email'    => setting('store_email'),
    'address'  => setting('store_address'),
    'hours'    => setting('store_hours'),
    'errors'   => $errors,
    'old'      => $old,
    'user'     => $user,
]);
