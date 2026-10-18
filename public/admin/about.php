<?php
/** GiftAura - admin: edit the About page (texts, founder photo, location and map). */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$admin = Auth::requireAdmin();
$errors = [];
$old = AboutService::get();

if (is_post()) {
    Csrf::verify();
    $input = [];
    foreach (array_keys(AboutService::FIELDS) as $key) {
        $input[$key] = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
    }
    $input['show_map'] = post_string('show_map');
    $photo = isset($_FILES['founder_photo']) && is_array($_FILES['founder_photo']) && !is_array($_FILES['founder_photo']['name'] ?? null) ? $_FILES['founder_photo'] : null;
    $result = AboutService::save($input, $photo, post_string('remove_photo') === '1', $admin['id']);
    if ($result === []) {
        Flash::add('success', 'About page saved.');
        redirect('admin/about.php');
    }
    $errors = $result['errors'];
    $old = $result['old'] + ['founder_photo' => $old['founder_photo']];
}

View::render('admin/about', [
    'title'  => 'About page',
    'old'    => $old,
    'errors' => $errors,
]);
