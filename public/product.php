<?php
/**
 * GiftAura - product detail page: product.php?slug=sample-red-rose-bouquet
 * Inactive products, or products in an inactive category, return 404.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$slug = query_string('slug');
$product = preg_match('/^[a-z0-9-]{1,170}$/', $slug) ? ProductService::findBySlug($slug) : null;
if ($product === null) {
    abort(404, 'This product does not exist or is no longer available.');
}

$category = CategoryService::findById($product['category_id']);

View::render('shop/product', [
    'title'   => $product['name'],
    'product' => $product,
    'images'  => ProductService::images($product['id']),
    'trail'   => $category ? CategoryService::trail($category) : [],
    'related' => ProductService::related($product),
]);
