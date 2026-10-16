<?php
/** Side navigation for "My account" pages. */
$items = [
    ['account/index.php', 'account/', 'Profile'],
    ['account/orders.php', 'account/orders.php', 'My orders'],
    ['account/addresses.php', 'account/addresses.php', 'Addresses'],
    ['account/password.php', 'account/password.php', 'Change password'],
];
// "My orders" stays highlighted while viewing a single order
$isOrderPage = nav_active('account/order.php') !== '';
?>
<nav class="account-nav" aria-label="My account">
    <ul>
        <?php foreach ($items as [$file, $path, $label]): ?>
            <?php $active = nav_active($file) !== '' || ($isOrderPage && $file === 'account/orders.php'); ?>
            <li><a class="account-nav__link<?= $active ? ' is-active' : '' ?>" href="<?= e(url($path)) ?>"><?= e($label) ?></a></li>
        <?php endforeach; ?>
    </ul>
</nav>
