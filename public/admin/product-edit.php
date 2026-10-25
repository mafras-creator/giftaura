<?php
/**
 * GiftAura - admin: add or edit a product, its images and its stock.
 * GET ?id=ID edits a product; no id = new product.
 * POST actions: save, upload, primary, delete_image, alt, stock.
 */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$admin   = Auth::requireAdmin('products');
$id      = ctype_digit(query_string('id')) ? (int) query_string('id') : 0;
$product = $id > 0 ? ProductAdminService::find($id) : null;
if ($id > 0 && $product === null) {
    abort(404, 'Product not found.');
}
$errors = [];
$old = $product ?? [
    'is_active' => '1', 'is_featured' => '0', 'initial_stock' => '0',
    'is_customizable' => '0', 'custom_required' => '1', 'custom_max_length' => '100',
    'low_stock_threshold' => setting('default_low_stock_threshold', '5'),
];
// Form shows the normal price and the sale price; the database keeps the
// price paid (price) and the price before the discount (compare_at_price).
if ($product !== null && $product['compare_at_price'] !== null) {
    $old['sale_price'] = $product['price'];
    $old['price'] = $product['compare_at_price'];
}
$back = $id > 0 ? ['id' => $id] : [];

if (is_post()) {
    Csrf::verify();
    $action = post_string('action');
    $imageId = ctype_digit(post_string('image_id')) ? (int) post_string('image_id') : 0;

    if ($action === 'save') {
        $fields = ['name', 'slug', 'sku', 'category_id', 'short_description', 'description', 'price', 'sale_price',
                   'low_stock_threshold', 'initial_stock', 'is_active', 'is_featured',
                   'is_customizable', 'custom_label', 'custom_max_length', 'custom_required'];
        $input = [];
        foreach ($fields as $f) {
            $input[$f] = post_string($f);
        }
        $result = ProductAdminService::save($product ? $id : null, $input, $admin['id']);
        if (isset($result['id'])) {
            if ($product === null) {
                // New product: upload the photos chosen in the same form (up to 4).
                $photos = ProductAdminService::addImages($result['id'], ProductAdminService::uploadedFiles($_FILES['images'] ?? null), '', $admin['id']);
                Flash::add('success', 'Product created' . ($photos['added'] > 0 ? ' with ' . $photos['added'] . ' photo(s).' : '. You can add photos below.'));
                foreach ($photos['errors'] as $photoError) {
                    Flash::add('error', $photoError);
                }
            } else {
                Flash::add('success', 'Product saved.');
            }
            redirect('admin/product-edit.php', ['id' => $result['id']]);
        }
        $errors = $result['errors'];
        $old = $result['old'] + ($product ?? []);
    } elseif ($product === null) {
        abort(400, 'Save the product first.');
    } else {
        switch ($action) {
            case 'upload':
                $files = ProductAdminService::uploadedFiles($_FILES['images'] ?? null, $_FILES['image'] ?? null);
                if ($files === []) {
                    Flash::add('error', 'Choose an image to upload.');
                    break;
                }
                $photos = ProductAdminService::addImages($id, $files, post_string('alt_text'), $admin['id']);
                if ($photos['added'] > 0) {
                    Flash::add('success', $photos['added'] === 1 ? 'Image uploaded.' : $photos['added'] . ' images uploaded.');
                }
                foreach ($photos['errors'] as $photoError) {
                    Flash::add('error', $photoError);
                }
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
