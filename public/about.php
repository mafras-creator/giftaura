<?php
/** GiftAura - about the store and how ordering works. */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

View::render('shop/about', [
    'title' => 'About us',
    'tree'  => CategoryService::tree(),
]);
