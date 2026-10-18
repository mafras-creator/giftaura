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

// Message the customer typed before an "add to cart" error (shown again so it can be corrected).
$customOld = '';
if (isset($_SESSION['custom_old']) && is_array($_SESSION['custom_old'])) {
    if ((int) ($_SESSION['custom_old']['product_id'] ?? 0) === $product['id']) {
        $customOld = (string) ($_SESSION['custom_old']['text'] ?? '');
    }
    unset($_SESSION['custom_old']);
}

View::render('shop/product', [
    'title'   => $product['name'],
    'product' => $product,
    'images'  => ProductService::images($product['id']),
    'trail'   => $category ? CategoryService::trail($category) : [],
    'related' => ProductService::related($product, 10),
    'reviewSummary' => ReviewService::summary($product['id']),
    'reviews' => ReviewService::forProduct($product['id'], ctype_digit(query_string('rpage')) ? (int) query_string('rpage') : 1),
    'customOld' => $customOld !== '' ? $customOld : (CartService::messages()[$product['id']] ?? ''),
]);
