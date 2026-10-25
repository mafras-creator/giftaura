<?php
/**
 * GiftAura - admin: one in-shop sale as a printable invoice.
 * POST action=void (reason): cancel the invoice and put the stock back.
 */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$admin = Auth::requireAdmin('shop_sales');
$id = ctype_digit(query_string('id')) ? (int) query_string('id') : 0;
$sale = ShopSaleService::find($id);
if ($sale === null) {
    abort(404, 'Invoice not found.');
}

$voidError = null;
if (is_post()) {
    Csrf::verify();
    if (post_string('action') !== 'void') {
        abort(400, 'Unknown action.');
    }
    $voidError = ShopSaleService::void($id, mb_substr(post_string('reason'), 0, 500), $admin['id']);
    if ($voidError === null) {
        Flash::add('success', 'Invoice ' . $sale['invoice_number'] . ' was cancelled and the stock was put back.');
        redirect('admin/sale.php', ['id' => $id]);
    }
}

View::render('admin/sale', [
    'title'     => 'Invoice ' . $sale['invoice_number'],
    'sale'      => $sale,
    'items'     => ShopSaleService::items($id),
    'voidError' => $voidError,
    'shop'      => [
        'name'    => setting('store_name', 'GiftAura'),
        'address' => setting('store_address'),
        'phone'   => setting('store_phone'),
        'email'   => setting('store_email'),
    ],
]);
