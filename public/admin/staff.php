<?php
/**
 * GiftAura - admin: staff accounts (super administrator only).
 * GET ?edit=ID shows the edit form for that staff member.
 * POST actions: create, update, reset_password, deactivate, activate.
 * A generated password is shown once on the response; it is never stored readable.
 */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$admin = Auth::requireSuperAdmin();
$editId = ctype_digit(query_string('edit')) ? (int) query_string('edit') : 0;
$editing = $editId > 0 ? StaffService::findStaff($editId) : null;
if ($editId > 0 && $editing === null) {
    abort(404, 'Staff account not found.');
}
$errors = [];
$old = $editing !== null
    ? ['full_name' => $editing['full_name'], 'email' => $editing['email'] ?? '', 'permissions' => StaffService::cleanPermissions($editing['permissions'])]
    : ['permissions' => StaffService::DEFAULT_PERMISSIONS];
$newPassword = null; // ['name' => ..., 'username' => ..., 'password' => ...]

if (is_post()) {
    Csrf::verify();
    $id = ctype_digit(post_string('id')) ? (int) post_string('id') : 0;
    $input = [
        'full_name'   => post_string('full_name'),
        'username'    => post_string('username'),
        'email'       => post_string('email'),
        'permissions' => is_array($_POST['permissions'] ?? null) ? $_POST['permissions'] : [],
    ];
    switch (post_string('action')) {
        case 'create':
            $result = StaffService::create($input, $admin['id']);
            if (isset($result['id'])) {
                $newPassword = ['name' => $input['full_name'], 'username' => mb_strtolower($input['username']), 'password' => $result['password'], 'new' => true];
                $old = ['permissions' => StaffService::DEFAULT_PERMISSIONS];
            } else {
                $errors = $result['errors'];
                $old = $result['old'];
            }
            break;
        case 'update':
            $errors = StaffService::update($id, $input, $admin['id']);
            if ($errors === []) {
                Flash::add('success', 'Staff account saved.');
                redirect('admin/staff.php');
            }
            $old = $input + ['permissions' => []];
            $old['permissions'] = StaffService::cleanPermissions($input['permissions']);
            break;
        case 'reset_password':
            $staff = StaffService::findStaff($id);
            $password = StaffService::resetPassword($id, $admin['id']);
            if ($password === null || $staff === null) {
                abort(404, 'Staff account not found.');
            }
            $newPassword = ['name' => $staff['full_name'], 'username' => $staff['username'], 'password' => $password, 'new' => false];
            break;
        case 'deactivate':
        case 'activate':
            if (StaffService::setActive($id, post_string('action') === 'activate', $admin['id'])) {
                Flash::add('success', post_string('action') === 'activate' ? 'Staff account activated.' : 'Staff account deactivated. The person is logged out and cannot log in.');
            }
            redirect('admin/staff.php');
        default:
            abort(400, 'Unknown action.');
    }
}

View::render('admin/staff', [
    'title'       => 'Staff accounts',
    'accounts'    => StaffService::all(),
    'editing'     => $editing,
    'old'         => $old,
    'errors'      => $errors,
    'newPassword' => $newPassword,
]);
