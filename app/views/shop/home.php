<?php
/** Homepage. Variables: $user, $banners, $tree (categories with pictures), $featured, $latest, $popular. */
$storeName = setting('store_name', 'GiftAura');
?>
<?php if ($banners !== []): ?>
    <section class="hero-slider" aria-roledescription="carousel" aria-label="Offers" data-slider>
        <div class="hero-slider__track">
            <?php foreach ($banners as $i => $banner): ?>
                <?php
                $src = (string) ImageUploader::url($banner['image_file'], 'banners');
                $href = BannerService::href($banner['link_url']);
                $showText = (int) $banner['show_text'] === 1;
                $wholeLink = $href !== null && ($banner['button_text'] === null || !$showText);
                ?>
                <div class="slide<?= $i === 0 ? ' is-active' : '' ?>" role="group" aria-roledescription="slide"
                     aria-label="<?= e(($i + 1) . ' of ' . count($banners)) ?>" data-slide>
                    <?php if ($wholeLink): ?><a class="slide__link" href="<?= e($href) ?>"><?php endif; ?>
                    <img class="slide__img" src="<?= e($src) ?>" alt="<?= $showText ? '' : e($banner['title']) ?>"
                         width="1920" height="600"<?= $i === 0 ? ' fetchpriority="high"' : ' loading="lazy"' ?>>
                    <?php if ($showText): ?>
                        <div class="slide__content">
                            <div class="container">
                                <div class="slide__text">
                                    <h2 class="slide__title"><?= e($banner['title']) ?></h2>
                                    <?php if ($banner['subtitle'] !== null): ?><p class="slide__subtitle"><?= e($banner['subtitle']) ?></p><?php endif; ?>
                                    <?php if (!$wholeLink && $href !== null): ?>
                                        <a class="btn btn--yellow" href="<?= e($href) ?>"><?= e($banner['button_text']) ?></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($wholeLink): ?></a><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($banners) > 1): ?>
            <button type="button" class="hero-slider__arrow hero-slider__arrow--prev" data-slide-prev aria-label="Previous banner">
                <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button type="button" class="hero-slider__arrow hero-slider__arrow--next" data-slide-next aria-label="Next banner">
                <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <div class="hero-slider__dots">
                <?php foreach ($banners as $i => $banner): ?>
                    <button type="button" class="hero-slider__dot<?= $i === 0 ? ' is-active' : '' ?>" data-slide-to="<?= e((string) $i) ?>"
                            aria-label="Show banner <?= e((string) ($i + 1)) ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="welcome">
        <div class="container welcome__inner">
            <h1 class="welcome__title">Flowers, beauty and gifts, delivered with care.</h1>
            <p class="welcome__text">Order online and pay on delivery or by bank transfer.</p>
            <a class="btn btn--yellow" href="<?= e(url('products.php')) ?>">Shop all gifts</a>
        </div>
    </section>
<?php endif; ?>

<?php if ($tree !== []): ?>
    <section class="section section--tight">
        <div class="container">
            <h2 class="sr-only">Shop by category</h2>
            <ul class="category-circles">
                <?php foreach ($tree as $category): ?>
                    <li>
                        <a class="category-circle" href="<?= e(url('products.php', ['category' => $category['slug']])) ?>">
                            <span class="category-circle__image"><img src="<?= e($category['picture']) ?>" alt="" width="120" height="120" loading="lazy"></span>
                            <span class="category-circle__name"><?= e($category['name']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<?php if ($featured !== []): ?>
    <section class="section section--tight">
        <div class="container">
            <div class="section-heading">
                <h2 class="section-heading__title">Featured products</h2>
                <a class="section-heading__link" href="<?= e(url('products.php')) ?>">Browse all products</a>
            </div>
            <div class="product-grid">
                <?php foreach ($featured as $product): ?>
                    <?= View::partial('product-card', ['product' => $product]) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="section section--tight">
    <div class="container">
        <div class="section-heading">
            <h2 class="section-heading__title">New arrivals</h2>
            <a class="section-heading__link" href="<?= e(url('products.php', ['sort' => 'newest'])) ?>">See all new products</a>
        </div>
        <?php if ($latest === []): ?>
            <p class="empty-state">New products will appear here soon.</p>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($latest as $product): ?>
                    <?= View::partial('product-card', ['product' => $product]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($popular !== []): ?>
    <section class="section section--tight">
        <div class="container">
            <div class="section-heading">
                <h2 class="section-heading__title">Popular categories</h2>
            </div>
            <div class="popular-grid popular-grid--<?= e((string) count($popular)) ?>">
                <?php foreach ($popular as $i => $tile): ?>
                    <a class="popular-tile popular-tile--<?= e((string) ($i % 6)) ?>" href="<?= e(url('products.php', ['category' => $tile['slug']])) ?>">
                        <span class="popular-tile__text">
                            <span class="popular-tile__tagline"><?= e($tile['tagline']) ?></span>
                            <span class="popular-tile__name"><?= e($tile['name']) ?></span>
                            <span class="popular-tile__cta">Shop now</span>
                        </span>
                        <img class="popular-tile__img" src="<?= e($tile['picture']) ?>" alt="" width="160" height="160" loading="lazy">
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="promise">
    <div class="container promise__grid">
        <div class="promise__item">
            <svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3 10h18" stroke="currentColor" stroke-width="2"/></svg>
            <div><h3 class="promise__title">Pay your way</h3><p>Cash on delivery, or bank transfer checked by our team.</p></div>
        </div>
        <div class="promise__item">
            <svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="7" cy="18" r="1.8" fill="currentColor"/><circle cx="17" cy="18" r="1.8" fill="currentColor"/></svg>
            <div><h3 class="promise__title">Track every order</h3><p>Follow each step from confirmation to delivery.</p></div>
        </div>
        <div class="promise__item">
            <svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><path d="M12 3v18M7 7h7a3 3 0 010 6H9a3 3 0 000 6h8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <div><h3 class="promise__title">Clear prices in LKR</h3><p>Item prices, delivery and total shown before you order.</p></div>
        </div>
    </div>
</section>
