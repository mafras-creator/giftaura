<?php
/** Homepage. Variables: $categories (active top-level categories), $user. */
$descriptions = [
    'flower-bouquets' => 'Fresh arrangements for birthdays, anniversaries and every thank-you.',
    'beauty-creams'   => 'Skin-care favourites that make a thoughtful, everyday gift.',
    'gift-items'      => 'Gift boxes, keepsakes and little extras to complete the moment.',
];
?>
<section class="hero">
    <div class="container hero__inner">
        <div class="hero__copy">
            <p class="eyebrow"><?= e(setting('store_name', 'GiftAura')) ?></p>
            <h1 class="hero__title">Thoughtful gifts, <em>beautifully</em> chosen.</h1>
            <p class="hero__text">
                Flower bouquets, beauty creams and gift items, ordered online and paid on delivery or by bank transfer.
            </p>
            <div class="hero__actions">
                <?php if ($user === null): ?>
                    <a class="btn btn--primary" href="<?= e(url('register.php')) ?>">Create an account</a>
                    <a class="btn btn--outline" href="<?= e(url('login.php')) ?>">Log in</a>
                <?php else: ?>
                    <a class="btn btn--primary" href="<?= e(url('account/')) ?>">Go to my account</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="hero__art" aria-hidden="true">
            <div class="hero__ring"></div>
            <img src="<?= e(asset('img/logo.svg')) ?>" alt="" width="180" height="180">
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <header class="section__header">
            <p class="eyebrow">Our collections</p>
            <h2 class="section__title">Shop by category</h2>
        </header>

        <?php if ($categories === []): ?>
            <p class="empty-state">Categories will appear here once they are added by the store.</p>
        <?php else: ?>
            <div class="category-grid">
                <?php foreach ($categories as $i => $category): ?>
                    <article class="category-card category-card--<?= e((string) ($i % 3 + 1)) ?>">
                        <h3 class="category-card__name"><?= e($category['name']) ?></h3>
                        <p class="category-card__text">
                            <?= e($category['description'] ?: ($descriptions[$category['slug']] ?? '')) ?>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section section--tint">
    <div class="container feature-grid">
        <div class="feature">
            <h3 class="feature__title">Pay your way</h3>
            <p>Cash on delivery, or bank transfer confirmed by our team.</p>
        </div>
        <div class="feature">
            <h3 class="feature__title">Your own account</h3>
            <p>Save your details and follow every order from one place.</p>
        </div>
        <div class="feature">
            <h3 class="feature__title">Clear prices in LKR</h3>
            <p>Item prices, delivery charge and total shown before you order.</p>
        </div>
    </div>
</section>
