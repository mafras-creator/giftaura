<?php
/**
 * GiftAura - shop / product listing.
 *
 * Query parameters (all optional):
 *   category  category slug (includes its subcategories)
 *   q         search words
 *   min, max  price range in LKR
 *   in_stock  1 = only products with stock
 *   sort      newest | price_asc | price_desc | name
 *   page      page number
 * Every value is validated or whitelisted here before it is used.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$notices = [];

// Category
$category = null;
$categorySlug = query_string('category');
if ($categorySlug !== '') {
    $category = preg_match('/^[a-z0-9-]{1,120}$/', $categorySlug) ? CategoryService::findBySlug($categorySlug) : null;
    if ($category === null) {
        abort(404, 'This category does not exist or is not available at the moment.');
    }
}

// Search text (limited length)
$q = mb_substr(query_string('q'), 0, 100);

// Price range: whole or 2-decimal amounts only
$min = query_string('min');
$max = query_string('max');
foreach (['min' => &$min, 'max' => &$max] as $label => &$value) {
    if ($value !== '' && !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $value)) {
        $notices[] = 'The ' . ($label === 'min' ? 'minimum' : 'maximum') . ' price was ignored because it is not a valid amount.';
        $value = '';
    }
}
unset($value);
if ($min !== '' && $max !== '' && (float) $min > (float) $max) {
    [$min, $max] = [$max, $min];
}

$inStock = query_string('in_stock') === '1';
$sort = array_key_exists(query_string('sort'), ProductService::SORTS) ? query_string('sort') : 'newest';
$page = ctype_digit(query_string('page')) ? (int) query_string('page') : 1;

$result = ProductService::search([
    'category' => $category ? CategoryService::idsIncludingChildren($category['id']) : [],
    'q'        => $q,
    'min'      => $min,
    'max'      => $max,
    'in_stock' => $inStock,
    'sort'     => $sort,
    'page'     => $page,
]);

// Current filters, used to build pagination and "clear" links
$filters = array_filter([
    'category' => $category['slug'] ?? '',
    'q'        => $q,
    'min'      => $min,
    'max'      => $max,
    'in_stock' => $inStock ? '1' : '',
    'sort'     => $sort !== 'newest' ? $sort : '',
], static fn($v) => $v !== '');

$heading = $category['name'] ?? ($q !== '' ? 'Search results' : 'All products');

View::render('shop/products', [
    'title'      => $q !== '' ? 'Search: ' . $q : $heading,
    'heading'    => $heading,
    'category'   => $category,
    'trail'      => $category ? CategoryService::trail($category) : [],
    'tree'       => CategoryService::tree(),
    'q'          => $q,
    'min'        => $min,
    'max'        => $max,
    'inStock'    => $inStock,
    'sort'       => $sort,
    'result'     => $result,
    'filters'    => $filters,
    'priceRange' => ProductService::priceRange(),
    'notices'    => $notices,
]);
