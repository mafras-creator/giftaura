<?php
/** GiftAura - My account: profile card; ?edit=1 shows the form to change name and mobile number. */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$user   = Auth::requireLogin();
$errors = [];
$old    = [];

if (is_post()) {
    Csrf::verify();
    $input  = ['full_name' => $_POST['full_name'] ?? '', 'phone' => $_POST['phone'] ?? ''];
    $errors = UserService::updateProfile($user['id'], $input);
    if ($errors === []) {
        Flash::add('success', 'Your profile has been updated.');
        redirect('account/');
    }
    $old = ['full_name' => post_string('full_name'), 'phone' => post_string('phone')];
}

// Location shown on the profile card: the default (or newest) saved address.
$place = Database::fetchOne(
    'SELECT city, district FROM customer_addresses WHERE user_id = :u ORDER BY is_default DESC, id DESC LIMIT 1',
    ['u' => $user['id']]
);
$stats = [
    'orders'   => (int) Database::fetchValue('SELECT COUNT(*) FROM orders WHERE user_id = :u', ['u' => $user['id']]),
    'wishlist' => (int) Database::fetchValue('SELECT COUNT(*) FROM wishlist_items WHERE user_id = :u', ['u' => $user['id']]),
    'reviews'  => (int) Database::fetchValue('SELECT COUNT(*) FROM product_reviews WHERE user_id = :u', ['u' => $user['id']]),
];

View::render('shop/account/profile', [
    'title'   => 'My profile',
    'user'    => $user,
    'errors'  => $errors,
    'old'     => $old,
    'editing' => $errors !== [] || query_string('edit') === '1',
    'place'   => $place,
    'stats'   => $stats,
]);
