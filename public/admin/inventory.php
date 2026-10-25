<?php
/**
 * GiftAura - admin: stock overview, low-stock alerts and the stock ledger.
 * POST: quick stock adjustment for any product.
 */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$admin = Auth::requireAdmin('products');

if (is_post()) {
    Csrf::verify();
    $productId = ctype_digit(post_string('product_id')) ? (int) post_string('product_id') : 0;
    $error = ProductAdminService::adjustStock($productId, post_string('mode'), post_string('quantity'), post_string('note'), $admin['id']);
    Flash::add($error === null ? 'success' : 'error', $error ?? 'Stock updated.');
    redirect('admin/inventory.php', $productId ? ['product' => $productId] : []);
}

$productFilter = ctype_digit(query_string('product')) ? (int) query_string('product') : null;

View::render('admin/inventory', [
    'title'         => 'Inventory',
    'lowStock'      => ReportService::inventory(true),
    'products'      => Database::fetchAll('SELECT id, name, sku, stock_quantity FROM products ORDER BY name'),
    'productFilter' => $productFilter,
    'movements'     => ProductAdminService::movements($productFilter, ctype_digit(query_string('page')) ? (int) query_string('page') : 1),
]);
