<?php
/** GiftAura - admin: store settings (contact details, delivery charges, payment methods, bank details). */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$admin = Auth::requireAdmin();
$errors = [];
$submitted = [];

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
