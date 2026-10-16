<?php
/**
 * GiftAura - admin: add or edit a product, its images and its stock.
 * GET ?id=ID edits a product; no id = new product.
 * POST actions: save, upload, primary, delete_image, alt, stock.
 */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$admin   = Auth::requireAdmin();
$id      = ctype_digit(query_string('id')) ? (int) query_string('id') : 0;
$product = $id > 0 ? ProductAdminService::find($id) : null;
if ($id > 0 && $product === null) {
    abort(404, 'Product not found.');
}
$errors = [];
$old = $product ?? [
    'is_active' => '1', 'is_featured' => '0', 'initial_stock' => '0',
    'low_stock_threshold' => setting('default_low_stock_threshold', '5'),
];
$back = $id > 0 ? ['id' => $id] : [];

if (is_post()) {
    Csrf::verify();
    $action = post_string('action');
    $imageId = ctype_digit(post_string('image_id')) ? (int) post_string('image_id') : 0;

    if ($action === 'save') {
        $fields = ['name', 'slug', 'sku', 'category_id', 'short_description', 'description', 'price',
                   'low_stock_threshold', 'initial_stock', 'is_active', 'is_featured'];
        $input = [];
        foreach ($fields as $f) {
            $input[$f] = post_string($f);
        }
        $result = ProductAdminService::save($product ? $id : null, $input, $admin['id']);
        if (isset($result['id'])) {
            Flash::add('success', $product ? 'Product saved.' : 'Product created. You can now add images.');
            redirect('admin/product-edit.php', ['id' => $result['id']]);
        }
        $errors = $result['errors'];
        $old = $result['old'] + ($product ?? []);
    } elseif ($product === null) {
        abort(400, 'Save the product first.');
    } else {
        switch ($action) {
            case 'upload':
                $file = $_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE];
                if (!is_array($file) || is_array($file['name'] ?? null)) {
                    $file = ['error' => UPLOAD_ERR_NO_FILE];
                }
                $error = ProductAdminService::addImage($id, $file, post_string('alt_text'), $admin['id']);
                Flash::add($error === null ? 'success' : 'error', $error ?? 'Image uploaded.');
                break;
            case 'primary':
                Flash::add('success', ProductAdminService::setPrimaryImage($id, $imageId, $admin['id']) ? 'Main image changed.' : 'Image not found.');
                break;
            case 'delete_image':
                Flash::add('success', ProductAdminService::deleteImage($id, $imageId, $admin['id']) ? 'Image deleted.' : 'Image not found.');
                break;
            case 'alt':
                ProductAdminService::updateAltText($id, $imageId, post_string('alt_text'));
                Flash::add('success', 'Image description saved.');
                break;
            case 'stock':
                $error = ProductAdminService::adjustStock($id, post_string('mode'), post_string('quantity'), post_string('note'), $admin['id']);
                Flash::add($error === null ? 'success' : 'error', $error ?? 'Stock updated.');
                break;
            default:
                abort(400, 'Unknown action.');
        }
        redirect('admin/product-edit.php', ['id' => $id]);
    }
}

View::render('admin/product-edit', [
    'title'      => $product ? 'Edit product' : 'Add product',
    'product'    => $product,
    'old'        => $old,
    'errors'     => $errors,
    'categories' => CategoryAdminService::options(),
    'images'     => $product ? ProductAdminService::images($id) : [],
    'movements'  => $product ? ProductAdminService::movements($id, 1, 10)['items'] : [],
]);
