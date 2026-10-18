<?php
/**
 * GiftAura - admin: homepage banners (slides).
 * GET ?edit=ID shows the edit form. POST actions: save, show, hide, delete.
 */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$admin   = Auth::requireAdmin();
$errors  = [];
$editId  = ctype_digit(query_string('edit')) ? (int) query_string('edit') : 0;
$editing = $editId > 0 ? BannerService::find($editId) : null;
if ($editId > 0 && $editing === null) {
    abort(404, 'Banner not found.');
}
$old = $editing ?? ['is_active' => '1', 'show_text' => '1', 'sort_order' => '0'];

if (is_post()) {
    Csrf::verify();
    $id = ctype_digit(post_string('id')) ? (int) post_string('id') : 0;
    switch (post_string('action')) {
        case 'save':
            $input = [];
            foreach (['title', 'subtitle', 'button_text', 'link_url', 'sort_order', 'starts_on', 'ends_on', 'show_text', 'is_active'] as $f) {
                $input[$f] = post_string($f);
            }
            $file = isset($_FILES['image']) && is_array($_FILES['image']) ? $_FILES['image'] : null;
            $result = BannerService::save($id > 0 ? $id : null, $input, $file, $admin['id']);
            if (isset($result['id'])) {
                Flash::add('success', $id > 0 ? 'Banner updated.' : 'Banner added. It is shown on the homepage when it is active and within its dates.');
                redirect('admin/banners.php');
            }
            $errors = $result['errors'];
            $old = $result['old'];
            $editing = $id > 0 ? BannerService::find($id) : null;
            break;
        case 'show':
        case 'hide':
            BannerService::setActive($id, post_string('action') === 'show', $admin['id']);
            Flash::add('success', post_string('action') === 'show' ? 'Banner switched on.' : 'Banner hidden from the homepage.');
            redirect('admin/banners.php');
        case 'delete':
            Flash::add('success', BannerService::delete($id, $admin['id']) ? 'Banner deleted.' : 'Banner not found.');
            redirect('admin/banners.php');
        default:
            abort(400, 'Unknown action.');
    }
}

View::render('admin/banners', [
    'title'   => 'Homepage banners',
    'banners' => BannerService::all(),
    'editing' => $editing,
    'errors'  => $errors,
    'old'     => $old,
]);
