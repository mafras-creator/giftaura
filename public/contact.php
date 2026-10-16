<?php
/**
 * GiftAura - contact and store information.
 * All details come from the settings table (Admin -> Settings in Phase 6).
 * Empty settings are hidden; nothing is invented.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

View::render('shop/contact', [
    'title'    => 'Contact us',
    'phone'    => setting('store_phone'),
    'whatsapp' => setting('store_whatsapp'),
    'email'    => setting('store_email'),
    'address'  => setting('store_address'),
    'hours'    => setting('store_hours'),
]);
