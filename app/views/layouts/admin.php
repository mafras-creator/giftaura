<?php
/**
 * Administration layout: sidebar, top bar, flash messages.
 * Variables: $content, $title.
 */
$user      = current_user();
$storeName = setting('store_name', 'GiftAura');
$flashes   = Flash::pull();

// [file to highlight, link, label, other files that highlight the same item]
$menu = [
    ['admin/index.php', 'admin/', 'Dashboard', []],
    ['admin/orders.php', 'admin/orders.php', 'Orders', ['admin/order.php']],
    ['admin/products.php', 'admin/products.php', 'Products', ['admin/product-edit.php']],
    ['admin/categories.php', 'admin/categories.php', 'Categories', []],
    ['admin/inventory.php', 'admin/inventory.php', 'Inventory', []],
    ['admin/customers.php', 'admin/customers.php', 'Customers', ['admin/customer.php']],
    ['admin/reports.php', 'admin/reports.php', 'Reports', []],
    ['admin/activity.php', 'admin/activity.php', 'Activity log', []],
    ['admin/settings.php', 'admin/settings.php', 'Settings', []],
];
$pendingCount = (int) Database::fetchValue("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($title ?? 'Admin') . ' | ' . $storeName . ' Admin') ?></title>
    <link rel="icon" href="<?= e(asset('img/logo.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
    <script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</head>
<body class="admin">
<a class="skip-link" href="#admin-main">Skip to content</a>

<div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Admin navigation">
        <a class="admin-brand" href="<?= e(url('admin/')) ?>">
            <img src="<?= e(asset('img/logo.svg')) ?>" alt="" width="34" height="34">
            <span><?= e($storeName) ?><small>Administration</small></span>
        </a>
        <nav>
            <ul class="admin-menu">
                <?php foreach ($menu as [$file, $path, $label, $also]): ?>
                    <?php $active = nav_active($file) !== '' || array_filter($also, static fn($f) => nav_active($f) !== '') !== []; ?>
                    <li>
                        <a class="admin-menu__link<?= $active ? ' is-active' : '' ?>" href="<?= e(url($path)) ?>">
                            <?= e($label) ?>
                            <?php if ($file === 'admin/orders.php' && $pendingCount > 0): ?>
                                <span class="admin-menu__count" title="Pending orders"><?= e((string) $pendingCount) ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <ul class="admin-menu admin-menu--secondary">
                <li><a class="admin-menu__link" href="<?= e(url()) ?>">View shop</a></li>
            </ul>
        </nav>
    </aside>

    <div class="admin-body">
        <header class="admin-topbar">
            <button class="admin-topbar__toggle" type="button" aria-controls="admin-sidebar" aria-expanded="false" data-sidebar-toggle>
                <span class="sr-only">Toggle menu</span>&#9776;
            </button>
            <h1 class="admin-topbar__title"><?= e($title ?? 'Admin') ?></h1>
            <div class="admin-topbar__user">
                <span class="admin-topbar__name"><?= e($user['full_name'] ?? '') ?></span>
                <a href="<?= e(url('account/password.php')) ?>">Change password</a>
                <form method="post" action="<?= e(url('logout.php')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--ghost btn--small">Log out</button>
                </form>
            </div>
        </header>

        <main id="admin-main" class="admin-main">
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert--<?= e($flash['type']) ?>" role="status">
                    <span><?= e($flash['message']) ?></span>
                    <button type="button" class="alert__close" data-dismiss aria-label="Close message">&times;</button>
                </div>
            <?php endforeach; ?>

            <?= $content ?>
        </main>
    </div>
</div>
</body>
</html>
