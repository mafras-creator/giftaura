<?php
/**
 * GiftAura - admin: categories and subcategories.
 * GET ?edit=ID shows the edit form; ?parent=ID starts a new subcategory of that main category.
 * POST actions: save, activate, deactivate, delete.
 */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$admin   = Auth::requireAdmin('categories');
$errors  = [];
$editId  = ctype_digit(query_string('edit')) ? (int) query_string('edit') : 0;
$editing = $editId > 0 ? CategoryAdminService::find($editId) : null;
if ($editId > 0 && $editing === null) {
    abort(404, 'Category not found.');
}
// "Add subcategory" button: the form starts with that main category chosen.
$parentForNew = ctype_digit(query_string('parent')) ? CategoryAdminService::find((int) query_string('parent')) : null;
if ($parentForNew !== null && $parentForNew['parent_id'] !== null) {
    $parentForNew = null; // only main categories can have subcategories
}
$old = $editing ?? ['is_active' => '1', 'sort_order' => '0', 'parent_id' => $parentForNew !== null ? (string) $parentForNew['id'] : ''];

if (is_post()) {
    Csrf::verify();
    $id = ctype_digit(post_string('id')) ? (int) post_string('id') : 0;
    switch (post_string('action')) {
        case 'save':
            $input = [];
            foreach (['name', 'slug', 'description', 'sort_order', 'parent_id', 'is_active'] as $f) {
                $input[$f] = post_string($f);
            }
            $result = CategoryAdminService::save($id > 0 ? $id : null, $input, $admin['id']);
            if (isset($result['id'])) {
                $savedName = $input['name'];
                $file = isset($_FILES['image']) && is_array($_FILES['image']) ? $_FILES['image'] : null;
                $imageError = CategoryAdminService::setImage($result['id'], $file, post_string('remove_image') === '1', $admin['id']);
                if ($imageError !== null) {
                    Flash::add('error', $imageError);
                }
                Flash::add('success', $id > 0
                    ? 'Category "' . $savedName . '" updated.'
                    : ($input['parent_id'] !== '' ? 'Subcategory "' . $savedName . '" added.' : 'Main category "' . $savedName . '" added.'));
                redirect('admin/categories.php');
            }
            $errors = $result['errors'];
            $old = $result['old'];
            $editing = $id > 0 ? CategoryAdminService::find($id) : null;
            break;
        case 'popular':
            $ids = is_array($_POST['popular_id'] ?? null) ? $_POST['popular_id'] : [];
            $lines = is_array($_POST['popular_tagline'] ?? null) ? $_POST['popular_tagline'] : [];
            $error = PopularService::save($ids, $lines, $admin['id']);
            Flash::add($error === null ? 'success' : 'error', $error ?? 'Popular categories saved. They are shown near the bottom of the homepage.');
            redirect('admin/categories.php');
        case 'activate':
        case 'deactivate':
            CategoryAdminService::setActive($id, post_string('action') === 'activate', $admin['id']);
            Flash::add('success', post_string('action') === 'activate' ? 'Category activated.' : 'Category deactivated. Its products are hidden from the shop.');
            redirect('admin/categories.php');
        case 'delete':
            $error = CategoryAdminService::delete($id, $admin['id']);
            Flash::add($error === null ? 'success' : 'error', $error ?? 'Category deleted.');
            redirect('admin/categories.php');
        default:
            abort(400, 'Unknown action.');
    }
}

View::render('admin/categories', [
    'title'      => 'Categories',
    'categories' => CategoryAdminService::all(),
    'parents'    => CategoryAdminService::parentOptions($editing ? (int) $editing['id'] : null),
    'editing'    => $editing,
    'parentForNew' => $editing === null ? $parentForNew : null,
    'errors'     => $errors,
    'old'        => $old,
]);
