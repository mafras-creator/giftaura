<?php
/**
 * Administration layout: sidebar, top bar, flash messages.
 * Variables: $content, $title.
 */
$user      = current_user();
$storeName = setting('store_name', 'GiftAura');
$flashes   = Flash::pull();

// [file to highlight, link, label, other files that highlight the same item, area ('' = everyone, 'super' = super admin only)]
$menu = [
    ['admin/index.php', 'admin/', 'Dashboard', [], ''],
    ['admin/orders.php', 'admin/orders.php', 'Orders', ['admin/order.php'], 'orders'],
    ['admin/sales.php', 'admin/sales.php', 'In-shop sales', ['admin/sale.php', 'admin/sale-new.php'], 'shop_sales'],
    ['admin/inquiries.php', 'admin/inquiries.php', 'Inquiries', ['admin/inquiry.php'], 'inquiries'],
    ['admin/products.php', 'admin/products.php', 'Products', ['admin/product-edit.php'], 'products'],
    ['admin/categories.php', 'admin/categories.php', 'Categories', [], 'categories'],
    ['admin/inventory.php', 'admin/inventory.php', 'Inventory', [], 'products'],
    ['admin/customers.php', 'admin/customers.php', 'Customers', ['admin/customer.php'], 'customers'],
    ['admin/reviews.php', 'admin/reviews.php', 'Reviews', [], 'reviews'],
    ['admin/banners.php', 'admin/banners.php', 'Banners', [], 'content'],
    ['admin/about.php', 'admin/about.php', 'About page', [], 'content'],
    ['admin/reports.php', 'admin/reports.php', 'Reports', [], 'reports'],
    ['admin/staff.php', 'admin/staff.php', 'Staff accounts', [], 'super'],
    ['admin/emails.php', 'admin/emails.php', 'E-mail log', [], 'super'],
    ['admin/activity.php', 'admin/activity.php', 'Activity log', [], 'super'],
    ['admin/settings.php', 'admin/settings.php', 'Settings', [], 'super'],
];
$menu = array_values(array_filter($menu, static fn($m) => $m[4] === '' || ($m[4] === 'super' ? Auth::isSuperAdmin() : Auth::can($m[4]))));
$newInquiryCount = Auth::can('inquiries') ? InquiryService::countNew() : 0;
$pendingCount = Auth::can('orders') ? (int) Database::fetchValue("SELECT COUNT(*) FROM orders WHERE status = 'pending'") : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($title ?? 'Admin') . ' | ' . $storeName . ' Admin') ?></title>
    <link rel="icon" href="<?= e(BrandService::logoUrl()) ?>" type="<?= e(BrandService::logoType()) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
    <script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</head>
<body class="admin">
<a class="skip-link" href="#admin-main">Skip to content</a>

<div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Admin navigation">
        <a class="admin-brand<?= BrandService::isWide() ? ' admin-brand--wide' : '' ?>" href="<?= e(url('admin/')) ?>">
            <img class="admin-brand__logo<?= BrandService::hasCustomLogo() ? ' admin-brand__logo--custom' : '' ?><?= BrandService::isWide() ? ' admin-brand__logo--wide' : '' ?>"
                 src="<?= e(BrandService::logoUrl()) ?>" alt="" height="34"<?= BrandService::isWide() ? '' : ' width="34"' ?>>
            <span><?= BrandService::isWide() ? '' : e($storeName) ?><small>Administration</small></span>
        </a>
        <nav>
            <ul class="admin-menu">
                <?php foreach ($menu as [$file, $path, $label, $also, $area]): ?>
                    <?php $active = nav_active($file) !== '' || array_filter($also, static fn($f) => nav_active($f) !== '') !== []; ?>
                    <li>
                        <a class="admin-menu__link<?= $active ? ' is-active' : '' ?>" href="<?= e(url($path)) ?>">
                            <?= e($label) ?>
                            <?php if ($file === 'admin/orders.php' && $pendingCount > 0): ?>
                                <span class="admin-menu__count" title="Pending orders"><?= e((string) $pendingCount) ?></span>
                            <?php endif; ?>
                            <?php if ($file === 'admin/inquiries.php' && $newInquiryCount > 0): ?>
                                <span class="admin-menu__count" title="New inquiries"><?= e((string) $newInquiryCount) ?></span>
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
                <span class="admin-topbar__name"><?= e($user['full_name'] ?? '') ?>
                    <small class="admin-topbar__role"><?= ($user['role'] ?? '') === 'admin' ? 'Main administrator' : 'Staff' ?></small></span>
                <a href="<?= e(url('admin/password.php')) ?>">Change password</a>
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
