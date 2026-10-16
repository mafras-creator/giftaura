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
    <link rel="icon" href="<?= e(asset('img/logo.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/shop.css')) ?>">
    <script src="<?= e(asset('js/shop.js')) ?>" defer></script>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="<?= e(url()) ?>" aria-label="<?= e($storeName) ?> home">
            <img class="brand__mark" src="<?= e(asset('img/logo.svg')) ?>" alt="" width="40" height="40">
            <span class="brand__name"><?= e($storeName) ?></span>
        </a>

        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" data-nav-toggle>
            <span class="nav-toggle__bar"></span><span class="nav-toggle__bar"></span><span class="nav-toggle__bar"></span>
            <span class="sr-only">Menu</span>
        </button>

        <nav class="site-nav" id="site-nav" aria-label="Main">
            <ul class="site-nav__links">
                <li><a class="site-nav__link<?= nav_active('index.php') ?>" href="<?= e(url()) ?>">Home</a></li>
                <li><a class="site-nav__link<?= nav_active('products.php') ?>" href="<?= e(url('products.php')) ?>">Shop</a></li>
                <li><a class="site-nav__link<?= nav_active('about.php') ?>" href="<?= e(url('about.php')) ?>">About</a></li>
                <li><a class="site-nav__link<?= nav_active('contact.php') ?>" href="<?= e(url('contact.php')) ?>">Contact</a></li>
            </ul>
            <form class="header-search" method="get" action="<?= e(url('products.php')) ?>" role="search">
                <label class="sr-only" for="header-q">Search products</label>
                <input type="search" id="header-q" name="q" maxlength="100" placeholder="Search products"
                       value="<?= e(nav_active('products.php') !== '' ? query_string('q') : '') ?>">
                <button type="submit" class="header-search__button" aria-label="Search">
                    <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="M20 20l-4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
            </form>
            <ul class="site-nav__account">
                <li>
                    <a class="cart-link<?= nav_active('cart.php') ?>" href="<?= e(url('cart.php')) ?>">
                        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M6 6h15l-1.5 9h-12z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M6 6 5 3H2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="9" cy="20" r="1.5" fill="currentColor"/><circle cx="18" cy="20" r="1.5" fill="currentColor"/></svg>
                        <span>Cart</span>
                        <?php if ($cartCount > 0): ?><span class="cart-link__count"><?= e((string) $cartCount) ?></span><span class="sr-only"> items</span><?php endif; ?>
                    </a>
                </li>
                <?php if ($user === null): ?>
                    <li><a class="site-nav__link<?= nav_active('login.php') ?>" href="<?= e(url('login.php')) ?>">Log in</a></li>
                    <li><a class="btn btn--primary btn--small" href="<?= e(url('register.php')) ?>">Create account</a></li>
                <?php else: ?>
                    <?php if ($user['role'] === 'admin'): ?>
                        <li><a class="site-nav__link" href="<?= e(url('admin/')) ?>">Admin panel</a></li>
                    <?php endif; ?>
                    <li><a class="site-nav__link<?= nav_active('account/index.php') ?>" href="<?= e(url('account/')) ?>">My account</a></li>
                    <li>
                        <form class="inline-form" method="post" action="<?= e(url('logout.php')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn--ghost btn--small">Log out</button>
                        </form>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
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

    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="container site-footer__grid">
        <div>
            <p class="site-footer__brand"><?= e($storeName) ?></p>
            <p class="site-footer__text"><?= e(setting('store_tagline')) ?></p>
            <p class="site-footer__text site-footer__note">Cash on delivery and bank transfer. Prices in Sri Lankan rupees (LKR).</p>
        </div>
        <div>
            <h2 class="site-footer__heading">Shop</h2>
            <ul class="site-footer__links">
                <?php foreach (CategoryService::topLevel() as $footerCategory): ?>
                    <li><a href="<?= e(url('products.php', ['category' => $footerCategory['slug']])) ?>"><?= e($footerCategory['name']) ?></a></li>
                <?php endforeach; ?>
                <li><a href="<?= e(url('about.php')) ?>">About us</a></li>
                <li><a href="<?= e(url('contact.php')) ?>">Contact us</a></li>
            </ul>
        </div>
        <div>
            <h2 class="site-footer__heading">Your account</h2>
            <ul class="site-footer__links">
                <?php if ($user === null): ?>
                    <li><a href="<?= e(url('login.php')) ?>">Log in</a></li>
                    <li><a href="<?= e(url('register.php')) ?>">Create an account</a></li>
                <?php endif; ?>
                <li><a href="<?= e(url('track-order.php')) ?>">Track your order</a></li>
                <?php if ($user !== null): ?>
                    <li><a href="<?= e(url('account/orders.php')) ?>">My orders</a></li>
                    <li><a href="<?= e(url('account/')) ?>">My profile</a></li>
                <?php endif; ?>
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
