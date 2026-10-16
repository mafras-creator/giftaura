<?php
/**
 * GiftAura - homepage.
 * Phase 3: hero and categories from the database.
 * Phase 4 adds featured and latest products and category links.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

View::render('shop/home', [
    'title'      => 'Home',
    'user'       => current_user(),
    'categories' => CategoryService::topLevel(),
]);
