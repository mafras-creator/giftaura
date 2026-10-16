<?php
/**
 * GiftAura - My account: address book.
 * GET ?edit=ID shows the edit form. POST actions: create, update, delete, default.
 */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$user   = Auth::requireLogin();
$errors = [];
$old    = [];
$editId = ctype_digit(query_string('edit')) ? (int) query_string('edit') : 0;
$editing = $editId > 0 ? AddressService::find($user['id'], $editId) : null;
if ($editId > 0 && $editing === null) {
    abort(404, 'Address not found.');
}

$fields = ['label', 'recipient_name', 'phone', 'address_line1', 'address_line2', 'city', 'district', 'postal_code'];

if (is_post()) {
    Csrf::verify();
    $action = post_string('action');
    $addressId = ctype_digit(post_string('address_id')) ? (int) post_string('address_id') : 0;
    $input = [];
    foreach ($fields as $field) {
        $input[$field] = post_string($field);
    }

    switch ($action) {
        case 'create':
            $result = AddressService::create($user['id'], $input, post_string('is_default') === '1');
            if (isset($result['id'])) {
                Flash::add('success', 'Address saved.');
                redirect('account/addresses.php');
            }
            $errors = $result['errors'];
            $old = $input;
            break;

        case 'update':
            $errors = AddressService::update($user['id'], $addressId, $input);
            if ($errors === []) {
                if (post_string('is_default') === '1') {
                    AddressService::setDefault($user['id'], $addressId);
                }
                Flash::add('success', 'Address updated.');
                redirect('account/addresses.php');
            }
            $old = $input;
            $editing = AddressService::find($user['id'], $addressId);
            break;

        case 'delete':
            $deleted = AddressService::delete($user['id'], $addressId);
            Flash::add($deleted ? 'success' : 'error', $deleted ? 'Address deleted.' : 'Address not found.');
            redirect('account/addresses.php');

        case 'default':
            $ok = AddressService::setDefault($user['id'], $addressId);
            Flash::add($ok ? 'success' : 'error', $ok ? 'Default address updated.' : 'Address not found.');
            redirect('account/addresses.php');

        default:
            abort(400, 'Unknown action.');
    }
}

View::render('shop/account/addresses', [
    'title'     => 'My addresses',
    'user'      => $user,
    'addresses' => AddressService::forUser($user['id']),
    'editing'   => $editing,
    'errors'    => $errors,
    'old'       => $old !== [] ? $old : ($editing ?? ['recipient_name' => $user['full_name'], 'phone' => $user['phone'] ?? '']),
]);
