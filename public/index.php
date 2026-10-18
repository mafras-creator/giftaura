<?php
/**
 * GiftAura - homepage: banners (Admin -> Banners), categories, featured and latest products.
 * Everything shown is read from the database.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

View::render('shop/home', [
    'title'    => 'Home',
    'user'     => current_user(),
    'banners'  => BannerService::active(),
    'tree'     => CategoryService::tree(true),
    'featured' => ProductService::featured(10),
    'latest'   => ProductService::latest(10),
    'popular'  => PopularService::tiles(),
]);
