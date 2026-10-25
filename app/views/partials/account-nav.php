<?php
/** Side menu for "My account" pages: a white card with an icon per item (igp-style). */
$s = 'fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"';
$icons = [
    'profile'   => '<circle cx="12" cy="12" r="9.5" ' . $s . '/><circle cx="12" cy="10" r="3" ' . $s . '/><path d="M6.5 18.5c1.3-2.2 3.2-3.3 5.5-3.3s4.2 1.1 5.5 3.3" ' . $s . '/>',
    'orders'    => '<rect x="5" y="3.5" width="14" height="17" rx="2" ' . $s . '/><path d="M9 3.5v2h6v-2M8.5 10h7M8.5 14h7M8.5 17.5h4" ' . $s . '/>',
    'wishlist'  => '<path d="M12 20s-7-4.3-8.6-8.6C2.3 8.4 4.2 5 7.6 5c1.8 0 3.3 1 4.4 2.6C13.1 6 14.6 5 16.4 5c3.4 0 5.3 3.4 4.2 6.4C19 15.7 12 20 12 20z" ' . $s . '/>',
    'reviews'   => '<path d="M12 3.8l2.5 5.1 5.6.8-4 3.9 1 5.6-5.1-2.7-5.1 2.7 1-5.6-4-3.9 5.6-.8z" ' . $s . '/>',
    'inquiries' => '<path d="M4.5 5.5h15a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H10l-4.5 3.5v-3.5h-1a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1z" ' . $s . '/><path d="M8 10h8M8 13h5" ' . $s . '/>',
    'addresses' => '<path d="M12 21s-6.5-6.2-6.5-11a6.5 6.5 0 0 1 13 0c0 4.8-6.5 11-6.5 11z" ' . $s . '/><circle cx="12" cy="10" r="2.4" ' . $s . '/>',
    'password'  => '<rect x="5" y="10.5" width="14" height="10" rx="2" ' . $s . '/><path d="M8.5 10.5V7.5a3.5 3.5 0 0 1 7 0v3" ' . $s . '/>',
];
$items = [
    ['account/index.php', 'account/', 'My profile', 'profile'],
    ['account/orders.php', 'account/orders.php', 'My orders', 'orders'],
    ['account/wishlist.php', 'account/wishlist.php', 'My wishlist', 'wishlist'],
    ['account/reviews.php', 'account/reviews.php', 'My reviews', 'reviews'],
    ['account/inquiries.php', 'account/inquiries.php', 'My inquiries', 'inquiries'],
    ['account/addresses.php', 'account/addresses.php', 'Address book', 'addresses'],
    ['account/password.php', 'account/password.php', 'Change password', 'password'],
];
// "My orders" stays highlighted while viewing a single order; "My reviews" while writing one.
$isOrderPage = nav_active('account/order.php') !== '';
$isReviewPage = nav_active('account/review.php') !== '';
?>
<nav class="account-nav" aria-label="My account">
    <h1 class="sr-only">My account</h1>
    <ul>
        <?php foreach ($items as [$file, $path, $label, $icon]): ?>
            <?php $active = nav_active($file) !== '' || ($isOrderPage && $file === 'account/orders.php') || ($isReviewPage && $file === 'account/reviews.php'); ?>
            <li><a class="account-nav__link<?= $active ? ' is-active' : '' ?>" href="<?= e(url($path)) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                <svg class="account-nav__icon" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><?= $icons[$icon] ?></svg>
                <span><?= e($label) ?></span>
            </a></li>
        <?php endforeach; ?>
    </ul>
</nav>
