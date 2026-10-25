<?php
/**
 * Customer-facing layout: header, navigation, flash messages, footer.
 * Variables: $content (rendered page), $title (page title), optional $bodyClass.
 */
$user      = current_user();
$storeName = setting('store_name', 'GiftAura');
$flashes   = Flash::pull();
$phone     = setting('store_phone');
$email     = setting('store_email');
$address   = setting('store_address');
$hours     = setting('store_hours');
$cartCount = CartService::count();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(isset($title) ? $title . ' | ' . $storeName : $storeName) ?></title>
    <meta name="description" content="<?= e($storeName) ?> - flower bouquets, beauty creams and gift items in Sri Lanka.">
    <link rel="icon" href="<?= e(BrandService::logoUrl()) ?>" type="<?= e(BrandService::logoType()) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/shop.css')) ?>">
    <script src="<?= e(asset('js/shop.js')) ?>" defer></script>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="topbar">
        <div class="container topbar__inner">
            <a class="brand" href="<?= e(url()) ?>" aria-label="<?= e($storeName) ?> home">
                <?php $wideLogo = BrandService::isWide(); ?>
                <img class="brand__mark<?= BrandService::hasCustomLogo() ? ' brand__mark--custom' : '' ?><?= $wideLogo ? ' brand__mark--wide' : '' ?>"
                     src="<?= e(BrandService::logoUrl()) ?>" alt="" height="40"<?= $wideLogo ? '' : ' width="40"' ?>>
                <?php if (!$wideLogo): ?><span class="brand__name"><?= e($storeName) ?></span><?php endif; ?>
            </a>

            <form class="header-search" method="get" action="<?= e(url('products.php')) ?>" role="search">
                <label class="sr-only" for="header-q">Search products</label>
                <input type="search" id="header-q" name="q" maxlength="100" placeholder="Search flowers, creams, gifts…"
                       value="<?= e(nav_active('products.php') !== '' ? query_string('q') : '') ?>">
                <button type="submit" class="header-search__button" aria-label="Search">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2.4"/><path d="M20 20l-4-4" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                </button>
            </form>

            <ul class="header-actions">
                <li>
                    <a class="header-action<?= nav_active('account/wishlist.php') ?>" href="<?= e(url('account/wishlist.php')) ?>">
                        <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-9.3-9.2C1.5 8.1 3.6 4.5 7.2 4.5c2 0 3.6 1.1 4.8 2.8 1.2-1.7 2.8-2.8 4.8-2.8 3.6 0 5.7 3.6 4.5 6.8-1.8 4.6-9.3 9.2-9.3 9.2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                        <span class="header-action__label">Wishlist</span>
                        <?php if (($wishCount = WishlistService::count()) > 0): ?><span class="cart-link__count wish-count"><?= e((string) $wishCount) ?></span><span class="sr-only"> saved</span><?php endif; ?>
                    </a>
                </li>
                <li>
                    <a class="header-action cart-link<?= nav_active('cart.php') ?>" href="<?= e(url('cart.php')) ?>">
                        <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M6 6h15l-1.5 9h-12z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M6 6 5 3H2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="9" cy="20" r="1.5" fill="currentColor"/><circle cx="18" cy="20" r="1.5" fill="currentColor"/></svg>
                        <span class="header-action__label">Cart</span>
                        <?php if ($cartCount > 0): ?><span class="cart-link__count"><?= e((string) $cartCount) ?></span><span class="sr-only"> items</span><?php endif; ?>
                    </a>
                </li>
                <?php if ($user === null): ?>
                    <li>
                        <a class="header-action<?= nav_active('login.php') ?>" href="<?= e(url('login.php')) ?>">
                            <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="2"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            <span class="header-action__label">Log in</span>
                        </a>
                    </li>
                    <li class="header-actions__wide"><a class="btn btn--yellow btn--small" href="<?= e(url('register.php')) ?>">Create account</a></li>
                <?php else: ?>
                    <?php if (Auth::isPanelRole($user['role'])): ?>
                        <li class="header-actions__wide"><a class="header-action" href="<?= e(url('admin/')) ?>"><span class="header-action__label">Admin panel</span></a></li>
                    <?php endif; ?>
                    <li>
                        <a class="header-action<?= nav_active('account/index.php') ?>" href="<?= e(url('account/')) ?>">
                            <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="2"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            <span class="header-action__label">My account</span>
                        </a>
                    </li>
                    <li>
                        <form class="inline-form" method="post" action="<?= e(url('logout.php')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="header-logout">Log out</button>
                        </form>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="subbar">
        <div class="container subbar__inner">
            <div class="catmenu">
                <button class="catmenu__toggle" type="button" aria-expanded="false" aria-controls="catmenu-panel" data-catmenu-toggle>
                    <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                    All categories
                </button>
                <div class="catmenu__panel" id="catmenu-panel" hidden>
                    <ul class="catmenu__list">
                        <li><a class="catmenu__link catmenu__link--all" href="<?= e(url('products.php')) ?>">All products</a></li>
                        <?php foreach (CategoryService::tree() as $navCategory): ?>
                            <li>
                                <a class="catmenu__link" href="<?= e(url('products.php', ['category' => $navCategory['slug']])) ?>"><?= e($navCategory['name']) ?></a>
                                <?php if ($navCategory['children'] !== []): ?>
                                    <ul class="catmenu__sub">
                                        <?php foreach ($navCategory['children'] as $navChild): ?>
                                            <li><a href="<?= e(url('products.php', ['category' => $navChild['slug']])) ?>"><?= e($navChild['name']) ?></a></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <nav class="site-nav" aria-label="Main">
                <a class="site-nav__link<?= nav_active('index.php') ?>" href="<?= e(url()) ?>">Home</a>
                <a class="site-nav__link<?= nav_active('products.php') !== '' && query_string('sale') !== '1' ? ' is-active' : '' ?>" href="<?= e(url('products.php')) ?>">Shop</a>
                <a class="site-nav__link site-nav__link--sale<?= query_string('sale') === '1' ? ' is-active' : '' ?>" href="<?= e(url('products.php', ['sale' => '1'])) ?>">On sale</a>
                <a class="site-nav__link<?= nav_active('track-order.php') ?>" href="<?= e(url('track-order.php')) ?>">Track your order</a>
                <a class="site-nav__link<?= nav_active('about.php') ?>" href="<?= e(url('about.php')) ?>">About</a>
                <a class="site-nav__link<?= nav_active('contact.php') ?>" href="<?= e(url('contact.php')) ?>">Contact</a>
            </nav>
        </div>
    </div>
</header>

<main id="main" class="site-main">
    <?php if ($flashes): ?>
        <div class="container flash-stack" role="status">
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert--<?= e($flash['type']) ?>">
                    <span><?= e($flash['message']) ?></span>
                    <button type="button" class="alert__close" data-dismiss aria-label="Close message">&times;</button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($user !== null && $user['role'] === 'customer' && $user['email_verified_at'] === null && empty($hideVerifyBanner)): ?>
        <div class="container flash-stack flash-stack--spaced">
            <div class="alert alert--warning verify-banner">
                <span>Please verify your e-mail address <strong><?= e($user['email']) ?></strong> — we sent you a link. You need a verified address to place an order.</span>
                <form class="inline-form" method="post" action="<?= e(url('resend-verification.php')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--ghost btn--small">Send the link again</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="container site-footer__grid">
        <div>
            <p class="site-footer__brand"><?= e($storeName) ?></p>
            <p class="site-footer__text"><?= e(setting('store_tagline')) ?></p>
            <p class="site-footer__text site-footer__note">Cash on delivery and bank transfer. Prices in Sri Lankan rupees (LKR).</p>
        </div>
        <div class="site-footer__shop">
            <h2 class="site-footer__heading">Shop</h2>
            <?php $footerCategories = CategoryService::topLevel(); ?>
            <ul class="site-footer__links site-footer__links--columns">
                <?php foreach (array_slice($footerCategories, 0, 12) as $footerCategory): ?>
                    <li><a href="<?= e(url('products.php', ['category' => $footerCategory['slug']])) ?>"><?= e($footerCategory['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <p class="site-footer__more">
                <a href="<?= e(url('products.php')) ?>"><?= count($footerCategories) > 12 ? 'View all ' . count($footerCategories) . ' categories' : 'View all products' ?></a>
                · <a href="<?= e(url('products.php', ['sale' => '1'])) ?>">On sale</a>
            </p>
        </div>
        <div>
            <h2 class="site-footer__heading">Customer care</h2>
            <ul class="site-footer__links">
                <?php if ($user === null): ?>
                    <li><a href="<?= e(url('login.php')) ?>">Log in</a></li>
                    <li><a href="<?= e(url('register.php')) ?>">Create an account</a></li>
                <?php else: ?>
                    <li><a href="<?= e(url('account/orders.php')) ?>">My orders</a></li>
                    <li><a href="<?= e(url('account/wishlist.php')) ?>">My wishlist</a></li>
                <?php endif; ?>
                <li><a href="<?= e(url('track-order.php')) ?>">Track your order</a></li>
                <li><a href="<?= e(url('about.php')) ?>">About us</a></li>
                <li><a href="<?= e(url('contact.php')) ?>">Contact us</a></li>
            </ul>
        </div>
        <div>
            <h2 class="site-footer__heading">Contact</h2>
            <?php if ($phone === '' && $email === '' && $address === ''): ?>
                <p class="site-footer__text">Store contact details will be published here soon.</p>
            <?php else: ?>
                <ul class="site-footer__links">
                    <?php if ($phone !== ''): ?><li><a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a></li><?php endif; ?>
                    <?php if ($email !== ''): ?><li><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
                    <?php if ($address !== ''): ?><li class="pre-line"><?= e($address) ?></li><?php endif; ?>
                    <?php if ($hours !== ''): ?><li class="pre-line"><?= e($hours) ?></li><?php endif; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <div class="container site-footer__bottom">
        <p>&copy; <?= e(date('Y')) ?> <?= e($storeName) ?>. All rights reserved.</p>
    </div>
</footer>
</body>
</html>
