<?php
/**
 * GiftAura - About page. Texts, founder photo and location are edited in
 * Admin -> About page (stored by AboutService). Numbers come from the database.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$about = AboutService::get();
$tree = CategoryService::tree();

// The map is a Google Maps frame: allow exactly that site in a frame on this page.
if ($about['show_map'] && $about['map_query'] !== '') {
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; "
         . "script-src 'self'; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; "
         . "object-src 'none'; frame-src https://www.google.com", true);
}

View::render('shop/about', [
    'title'    => 'About us',
    'about'    => $about,
    'tree'     => $tree,
    'products' => ProductService::search(['page' => 1])['total'],
    'payments' => PaymentService::enabledMethods(),
]);
