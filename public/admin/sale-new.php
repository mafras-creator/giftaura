<?php
/**
 * GiftAura - admin: record a sale made in the shop and create its invoice.
 * POST: product_id[], quantity[], customer_name, customer_phone, discount,
 * payment_method, amount_received, note. Prices are read from the database.
 */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$admin = Auth::requireAdmin('shop_sales');
$errors = [];
$old = ['payment_method' => 'cash', 'lines' => []];
// "Sell in shop" link from a product: start with that product on the first line.
if (ctype_digit(query_string('product'))) {
    $old['lines'][] = ['product_id' => query_string('product'), 'quantity' => '1'];
}

if (is_post()) {
    Csrf::verify();
    $result = ShopSaleService::create([
        'product_id'      => is_array($_POST['product_id'] ?? null) ? array_slice($_POST['product_id'], 0, 60) : [],
        'quantity'        => is_array($_POST['quantity'] ?? null) ? array_slice($_POST['quantity'], 0, 60) : [],
        'customer_name'   => post_string('customer_name'),
        'customer_phone'  => post_string('customer_phone'),
        'discount'        => post_string('discount'),
        'payment_method'  => post_string('payment_method'),
        'amount_received' => post_string('amount_received'),
        'note'            => mb_substr(post_string('note'), 0, 500),
    ], $admin['id']);
    if (isset($result['id'])) {
        Flash::add('success', 'Sale saved and stock updated. Print the invoice for the customer.');
        redirect('admin/sale.php', ['id' => $result['id']]);
    }
    $errors = $result['errors'];
    $old = $result['old'];
}

View::render('admin/sale-new', [
    'title'    => 'New in-shop sale',
    'products' => ShopSaleService::sellableProducts(),
    'errors'   => $errors,
    'old'      => $old,
]);
