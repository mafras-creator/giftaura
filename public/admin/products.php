<?php
/** GiftAura - admin: product list with search and filters. POST: activate / deactivate. */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$admin = Auth::requireAdmin('products');

if (is_post()) {
    Csrf::verify();
    $id = ctype_digit(post_string('id')) ? (int) post_string('id') : 0;
    $activate = post_string('action') === 'activate';
    if (!in_array(post_string('action'), ['activate', 'deactivate'], true)) {
        abort(400, 'Unknown action.');
    }
    ProductAdminService::setActive($id, $activate, $admin['id']);
    Flash::add('success', $activate ? 'Product activated. It is now visible in the shop.' : 'Product deactivated. It is hidden from the shop.');
    header('Location: ' . safe_next(post_string('return'), 'admin/products.php'), true, 303);
    exit;
}

$filters = [
    'q'        => mb_substr(query_string('q'), 0, 100),
    'category' => ctype_digit(query_string('category')) ? query_string('category') : '',
    'status'   => in_array(query_string('status'), ['active', 'inactive'], true) ? query_string('status') : '',
    'stock'    => in_array(query_string('stock'), ['low', 'out'], true) ? query_string('stock') : '',
];

View::render('admin/products', [
    'title'      => 'Products',
    'filters'    => $filters,
    'result'     => ProductAdminService::list($filters + ['page' => ctype_digit(query_string('page')) ? (int) query_string('page') : 1]),
    'categories' => CategoryAdminService::options(),
]);
