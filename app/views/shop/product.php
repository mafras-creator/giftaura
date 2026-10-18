<?php
/** Product detail. Variables: $product, $images, $trail, $related, $customOld (personalisation text to pre-fill), $reviewSummary, $reviews. */
$status = stock_status($product);
$main = $images[0] ?? null;
?>
<div class="container">
    <nav class="breadcrumbs breadcrumbs--page" aria-label="Breadcrumb">
        <a href="<?= e(url()) ?>">Home</a>
        <span aria-hidden="true">/</span>
        <a href="<?= e(url('products.php')) ?>">Shop</a>
        <?php foreach ($trail as $crumb): ?>
            <span aria-hidden="true">/</span>
            <a href="<?= e(url('products.php', ['category' => $crumb['slug']])) ?>"><?= e($crumb['name']) ?></a>
        <?php endforeach; ?>
        <span aria-hidden="true">/</span>
        <span aria-current="page"><?= e($product['name']) ?></span>
    </nav>

    <article class="product-detail">
        <div class="gallery">
            <div class="gallery__main">
                <?php if (($sale = sale_info($product)) !== null): ?><span class="sale-tag sale-tag--large">-<?= e((string) $sale['percent']) ?>%</span><?php endif; ?>
                <?= View::partial('wishlist-button', ['product' => $product, 'extraClass' => 'wish--large']) ?>
                <img id="gallery-main" src="<?= e(product_image_url($main['file_name'] ?? null)) ?>"
                     alt="<?= e($main['alt_text'] ?? $product['name']) ?>" width="800" height="800">
            </div>
            <?php if (count($images) > 1): ?>
                <ul class="gallery__thumbs" aria-label="More images">
                    <?php foreach ($images as $i => $image): ?>
                        <li>
                            <button type="button" class="gallery__thumb<?= $i === 0 ? ' is-active' : '' ?>"
                                    data-gallery-src="<?= e(product_image_url($image['file_name'])) ?>"
                                    data-gallery-alt="<?= e($image['alt_text'] ?: $product['name']) ?>"
                                    aria-label="Show image <?= e((string) ($i + 1)) ?>">
                                <img src="<?= e(product_image_url($image['file_name'])) ?>" alt="" width="96" height="96" loading="lazy">
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="product-info">
            <a class="product-info__category" href="<?= e(url('products.php', ['category' => $product['category_slug']])) ?>"><?= e($product['category_name']) ?></a>
            <h1 class="product-info__name"><?= e($product['name']) ?></h1>
            <?= View::partial('price', ['product' => $product, 'size' => 'large']) ?>
            <?php if ($reviewSummary['count'] > 0): ?>
                <a class="rating-link" href="#reviews"><?= View::partial('stars', ['rating' => $reviewSummary['average']]) ?>
                    <strong><?= e(number_format($reviewSummary['average'], 1)) ?></strong>
                    <span><?= e((string) $reviewSummary['count']) ?> review<?= $reviewSummary['count'] === 1 ? '' : 's' ?></span></a>
            <?php endif; ?>

            <p class="availability availability--<?= e($status['key']) ?>">
                <span class="availability__dot" aria-hidden="true"></span><?= e($status['label']) ?>
            </p>

            <?php if (!empty($product['short_description'])): ?>
                <p class="product-info__lead"><?= e($product['short_description']) ?></p>
            <?php endif; ?>

            <?php if ($status['key'] === 'out_of_stock'): ?>
                <div class="alert alert--info"><span>This item is currently out of stock. Please check again soon.</span></div>
            <?php else: ?>
                <?php $maxQty = min(CartService::maxPerItem(), (int) $product['stock_quantity']); ?>
                <form method="post" action="<?= e(url('cart.php')) ?>" class="add-to-cart">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= e((string) $product['id']) ?>">
                    <input type="hidden" name="return" value="<?= e(url('product.php', ['slug' => $product['slug']])) ?>">
                    <?php if ((int) $product['is_customizable'] === 1): ?>
                        <?php $customMax = (int) $product['custom_max_length']; $customRequired = (int) $product['custom_required'] === 1; ?>
                        <div class="personalise" id="personalise">
                            <p class="personalise__head">
                                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="m13.5 6.5 4 4" stroke="currentColor" stroke-width="2"/></svg>
                                <strong>Personalise it</strong>
                                <span class="personalise__tag"><?= $customRequired ? 'Required' : 'Optional' ?></span>
                            </p>
                            <label for="customization_text"><?= e(CartService::customLabel($product)) ?></label>
                            <textarea id="customization_text" name="customization_text" rows="3" maxlength="<?= e((string) $customMax) ?>"
                                      data-char-count aria-describedby="custom-help custom-count"<?= $customRequired ? ' required' : '' ?>><?= e($customOld) ?></textarea>
                            <p class="personalise__meta">
                                <span id="custom-help">We print it exactly as typed — please check the spelling.</span>
                                <span id="custom-count" data-char-counter aria-live="polite">Up to <?= e((string) $customMax) ?> characters</span>
                            </p>
                        </div>
                    <?php endif; ?>
                    <label class="sr-only" for="quantity">Quantity</label>
                    <div class="stepper">
                        <button type="button" class="stepper__btn" data-step="-1" aria-label="Decrease quantity">&minus;</button>
                        <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?= e((string) $maxQty) ?>" inputmode="numeric">
                        <button type="button" class="stepper__btn" data-step="1" aria-label="Increase quantity">+</button>
                    </div>
                    <button type="submit" class="btn btn--yellow btn--large add-to-cart__button">
                        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M6 6h15l-1.5 9h-12z" fill="currentColor"/><path d="M6 6 5 3H2" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><circle cx="9" cy="20" r="1.8" fill="currentColor"/><circle cx="18" cy="20" r="1.8" fill="currentColor"/></svg>
                        Add to cart
                    </button>
                </form>
            <?php endif; ?>

            <dl class="product-facts">
                <?php if (!empty($product['sku'])): ?>
                    <div><dt>Product code</dt><dd><?= e($product['sku']) ?></dd></div>
                <?php endif; ?>
                <div><dt>Category</dt><dd><a href="<?= e(url('products.php', ['category' => $product['category_slug']])) ?>"><?= e($product['category_name']) ?></a></dd></div>
                <?php if ((int) $product['is_customizable'] === 1): ?>
                    <div><dt>Personalisation</dt><dd>Your message is added to this gift</dd></div>
                <?php endif; ?>
                <div><dt>Payment</dt><dd>Cash on delivery or bank transfer</dd></div>
            </dl>

            <?php if (!empty($product['description'])): ?>
                <section class="product-description">
                    <h2>Description</h2>
                    <p class="pre-line"><?= e($product['description']) ?></p>
                </section>
            <?php endif; ?>
        </div>
    </article>
</div>

<?= View::partial('product-reviews', ['product' => $product, 'summary' => $reviewSummary, 'reviews' => $reviews]) ?>

<?php if ($related !== []): ?>
    <section class="section">
        <div class="container">
            <div class="section-heading">
                <h2 class="section-heading__title">Similar products</h2>
                <a class="section-heading__link" href="<?= e(url('products.php', ['category' => $product['category_slug']])) ?>">More in <?= e($product['category_name']) ?></a>
            </div>
            <div class="product-grid">
                <?php foreach ($related as $item): ?>
                    <?= View::partial('product-card', ['product' => $item]) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
