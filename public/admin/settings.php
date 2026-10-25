<?php
/** GiftAura - admin: store settings (logo, contact details, delivery charges, payment methods, bank details). */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$admin = Auth::requireSuperAdmin();
$errors = [];
$submitted = [];

if (is_post() && in_array(post_string('action'), ['logo_upload', 'logo_reset'], true)) {
    Csrf::verify();
    if (post_string('action') === 'logo_reset') {
        BrandService::reset($admin['id']);
        Flash::add('success', 'The default GiftAura logo is back.');
    } else {
        $file = isset($_FILES['logo']) && is_array($_FILES['logo']) && !is_array($_FILES['logo']['name'] ?? null) ? $_FILES['logo'] : ['error' => UPLOAD_ERR_NO_FILE];
        $error = BrandService::upload($file, $admin['id']);
        Flash::add($error === null ? 'success' : 'error', $error ?? 'New logo saved. Press Ctrl + F5 in the shop to see it.');
    }
    redirect('admin/settings.php');
}

if (is_post()) {
    Csrf::verify();
    foreach (SettingsService::rows() as $row) {
        $value = $_POST['settings'][$row['setting_key']] ?? null;
        if (is_string($value)) {
            $submitted[$row['setting_key']] = $value;
        }
    }
    $errors = SettingsService::update($submitted, $admin['id']);
    if ($errors === []) {
        Flash::add('success', 'Settings saved.');
        redirect('admin/settings.php');
    }
}

View::render('admin/settings', [
    'title'     => 'Settings',
    'rows'      => SettingsService::rows(),
    'errors'    => $errors,
    'submitted' => $submitted,
]);
