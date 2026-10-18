<?php
/** About page. Variables: $about (texts), $tree (categories), $products (count), $payments. */
$storeName = setting('store_name', 'GiftAura');
$photo = AboutService::founderPhotoUrl();
$phone = setting('store_phone');
$email = setting('store_email');
$hours = setting('store_hours');
$mapUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($about['map_query'] !== '' ? $about['map_query'] : $about['location']);
?>
<section class="about-hero">
    <div class="container about-hero__inner">
        <span class="about-hero__logo"><img src="<?= e(BrandService::logoUrl()) ?>" alt="<?= e($storeName) ?>" height="72"></span>
        <?php if ($about['location'] !== '' || $about['founded_year'] !== ''): ?>
            <p class="about-hero__pill">
                <?php if ($about['location'] !== ''): ?><span><?= e($about['location']) ?></span><?php endif; ?>
                <?php if ($about['founded_year'] !== ''): ?><span>Since <?= e($about['founded_year']) ?></span><?php endif; ?>
            </p>
        <?php endif; ?>
        <h1 class="about-hero__title"><?= e($about['headline']) ?><?php if ($about['headline_highlight'] !== ''): ?> <span><?= e($about['headline_highlight']) ?></span><?php endif; ?></h1>
        <?php if ($about['intro'] !== ''): ?><p class="about-hero__lead"><?= e($about['intro']) ?></p><?php endif; ?>
        <dl class="about-stats">
            <div><dt>Gifts to choose from</dt><dd><?= e((string) $products) ?></dd></div>
            <div><dt>Categories</dt><dd><?= e((string) count($tree)) ?></dd></div>
            <div><dt><?= count($payments) === 1 ? 'Way to pay' : 'Ways to pay' ?></dt><dd><?= e((string) count($payments)) ?></dd></div>
            <?php if ($about['founded_year'] !== ''): ?><div><dt>Serving since</dt><dd><?= e($about['founded_year']) ?></dd></div><?php endif; ?>
        </dl>
    </div>
</section>

<section class="section about-story">
    <div class="container about-story__grid">
        <div>
            <p class="about-kicker">Our story</p>
            <h2 class="about-title"><?= e($about['story_title']) ?></h2>
            <?php foreach (AboutService::paragraphs($about['story']) as $paragraph): ?>
                <p class="about-text"><?= e($paragraph) ?></p>
            <?php endforeach; ?>
        </div>
        <figure class="founder-card">
            <div class="founder-card__photo">
                <?php if ($photo !== null): ?>
                    <img src="<?= e($photo) ?>" alt="<?= e($about['founder_name']) ?>" width="128" height="128">
                <?php else: ?>
                    <span aria-hidden="true"><?= e(AboutService::initials($about['founder_name'])) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($about['founder_quote'] !== ''): ?>
                <blockquote class="founder-card__quote">“<?= e($about['founder_quote']) ?>”</blockquote>
            <?php endif; ?>
            <figcaption>
                <strong class="founder-card__name"><?= e($about['founder_name']) ?></strong>
                <?php if ($about['founder_role'] !== ''): ?><span class="founder-card__role"><?= e($about['founder_role']) ?>, <?= e($storeName) ?></span><?php endif; ?>
            </figcaption>
        </figure>
    </div>
</section>

<?php if ($about['mission'] !== '' || $about['vision'] !== ''): ?>
<section class="section section--tight">
    <div class="container about-mv">
        <?php if ($about['mission'] !== ''): ?>
            <article class="about-mv__card">
                <span class="about-mv__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="26" height="26"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="1.6" fill="currentColor"/></svg></span>
                <h2>Our mission</h2>
                <p><?= e($about['mission']) ?></p>
            </article>
        <?php endif; ?>
        <?php if ($about['vision'] !== ''): ?>
            <article class="about-mv__card">
                <span class="about-mv__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="26" height="26"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/></svg></span>
                <h2>Our vision</h2>
                <p><?= e($about['vision']) ?></p>
            </article>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<section class="section about-offer">
    <div class="container">
        <div class="about-center">
            <p class="about-kicker">What we offer</p>
            <h2 class="about-title">More than a gift box</h2>
        </div>
        <div class="offer-grid">
            <?php foreach ($tree as $i => $category): ?>
                <a class="offer-card" href="<?= e(url('products.php', ['category' => $category['slug']])) ?>">
                    <span class="offer-card__icon offer-card__icon--<?= e((string) ($i % 4)) ?>" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22"><rect x="3" y="9" width="18" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M2 9h20M12 9v12M12 9C9 9 7 7.5 7 6s1.5-2.5 3-2 2 3 2 5zm0 0c3 0 5-1.5 5-3s-1.5-2.5-3-2-2 3-2 5z" fill="none" stroke="currentColor" stroke-width="2"/></svg></span>
                    <h3><?= e($category['name']) ?></h3>
                    <p><?= e($category['description'] ?: 'Browse our ' . mb_strtolower($category['name']) . '.') ?></p>
                </a>
            <?php endforeach; ?>
            <div class="offer-card">
                <span class="offer-card__icon offer-card__icon--1" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22"><rect x="3" y="6" width="18" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3 10h18" stroke="currentColor" stroke-width="2"/></svg></span>
                <h3>Pay your way</h3>
                <p><?= e(ucfirst(implode(' or ', array_map('mb_strtolower', array_values($payments))) ?: 'Several payment options')) ?> — prices in Sri Lankan rupees, no card details needed.</p>
            </div>
            <div class="offer-card">
                <span class="offer-card__icon offer-card__icon--2" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="7" cy="18" r="1.8" fill="currentColor"/><circle cx="17" cy="18" r="1.8" fill="currentColor"/></svg></span>
                <h3>Follow every order</h3>
                <p>See each step from confirmation to delivery in My account, with an order reference for every purchase.</p>
            </div>
            <div class="offer-card">
                <span class="offer-card__icon offer-card__icon--3" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M4 5h16v11H8l-4 4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></span>
                <h3>Your gift message</h3>
                <p>Add a personal message or delivery note at checkout and we include it with your order.</p>
            </div>
        </div>
    </div>
</section>

<?php if ($about['location'] !== ''): ?>
<section class="section about-visit">
    <div class="container about-visit__grid">
        <div class="visit-card">
            <p class="about-kicker">Visit us</p>
            <?php $parts = array_map('trim', explode(',', $about['location'])); ?>
            <h2 class="about-title">Find us in <?= e($parts[1] ?? $parts[0]) ?></h2>
            <p class="visit-card__address">
                <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M12 21s-7-6.2-7-12a7 7 0 0114 0c0 5.8-7 12-7 12z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="9" r="2.5" fill="currentColor"/></svg>
                <span><?= setting('store_address') !== '' ? nl2br(e(setting('store_address'))) : e($about['location']) ?></span>
            </p>
            <?php if ($phone !== ''): ?><p class="visit-card__line"><strong>Phone</strong> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a></p><?php endif; ?>
            <?php if ($email !== ''): ?><p class="visit-card__line"><strong>E-mail</strong> <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p><?php endif; ?>
            <?php if ($hours !== ''): ?><p class="visit-card__line visit-card__line--hours"><strong>Opening hours</strong> <span><?= nl2br(e($hours)) ?></span></p><?php endif; ?>
            <div class="visit-card__actions">
                <a class="btn btn--yellow" href="<?= e($mapUrl) ?>" target="_blank" rel="noopener">Open in Google Maps</a>
                <a class="btn btn--outline" href="<?= e(url('contact.php')) ?>">Contact us</a>
            </div>
        </div>
        <?php if ($about['show_map'] && $about['map_query'] !== ''): ?>
            <div class="visit-map">
                <iframe title="Map: <?= e($about['location']) ?>" src="https://www.google.com/maps?q=<?= e(rawurlencode($about['map_query'])) ?>&amp;output=embed"
                        loading="lazy" referrerpolicy="no-referrer"></iframe>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
