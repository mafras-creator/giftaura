<?php
/** Homepage. Variables: $user, $tree (categories), $featured, $latest. */
?>
<section class="hero">
    <div class="container hero__inner">
        <div class="hero__copy">
            <p class="eyebrow"><?= e(setting('store_name', 'GiftAura')) ?></p>
            <h1 class="hero__title">Thoughtful gifts, <em>beautifully</em> chosen.</h1>
            <p class="hero__text">
                Flower bouquets, beauty creams and gift items, ordered online and paid on delivery or by bank transfer.
            </p>
            <form class="hero-search" method="get" action="<?= e(url('products.php')) ?>" role="search">
                <label class="sr-only" for="hero-q">Search products</label>
                <input type="search" id="hero-q" name="q" maxlength="100" placeholder="Search bouquets, creams, gifts…">
                <button type="submit" class="btn btn--primary">Search</button>
            </form>
            <div class="hero__actions">
                <a class="btn btn--outline" href="<?= e(url('products.php')) ?>">Browse all products</a>
                <?php if ($user === null): ?>
                    <a class="hero__link" href="<?= e(url('register.php')) ?>">Create an account</a>
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

        <?php if ($tree === []): ?>
            <p class="empty-state">Categories will appear here once they are added by the store.</p>
        <?php else: ?>
            <div class="category-grid">
                <?php foreach ($tree as $i => $category): ?>
                    <a class="category-card category-card--<?= e((string) ($i % 3 + 1)) ?>"
                       href="<?= e(url('products.php', ['category' => $category['slug']])) ?>">
                        <h3 class="category-card__name"><?= e($category['name']) ?></h3>
                        <p class="category-card__text"><?= e($category['description'] ?? '') ?></p>
                        <span class="category-card__more">
                            <?= e((string) $category['total_count']) ?> <?= $category['total_count'] === 1 ? 'product' : 'products' ?> &rarr;
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($featured !== []): ?>
<section class="section section--tint">
    <div class="container">
        <header class="section__header section__header--split">
            <div>
                <p class="eyebrow">Handpicked</p>
                <h2 class="section__title">Featured gifts</h2>
            </div>
            <a class="section__link" href="<?= e(url('products.php')) ?>">View all &rarr;</a>
        </header>
        <div class="product-grid product-grid--four">
            <?php foreach ($featured as $product): ?>
                <?= View::partial('product-card', ['product' => $product]) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <header class="section__header section__header--split">
            <div>
                <p class="eyebrow">Just arrived</p>
                <h2 class="section__title">Latest products</h2>
            </div>
            <a class="section__link" href="<?= e(url('products.php', ['sort' => 'newest'])) ?>">See more &rarr;</a>
        </header>
        <?php if ($latest === []): ?>
            <p class="empty-state">New products will appear here soon.</p>
        <?php else: ?>
            <div class="product-grid product-grid--four">
                <?php foreach ($latest as $product): ?>
                    <?= View::partial('product-card', ['product' => $product]) ?>
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
