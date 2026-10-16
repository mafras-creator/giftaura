<?php
/**
 * GiftAura - homepage: hero, categories, featured and latest products.
 * Everything shown is read from the database.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

View::render('shop/home', [
    'title'    => 'Home',
    'user'     => current_user(),
    'tree'     => CategoryService::tree(),
    'featured' => ProductService::featured(4),
    'latest'   => ProductService::latest(8),
]);
