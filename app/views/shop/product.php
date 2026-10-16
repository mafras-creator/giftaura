<?php
/** Product detail. Variables: $product, $images, $trail, $related. */
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
            <p class="eyebrow"><?= e($product['category_name']) ?></p>
            <h1 class="product-info__name"><?= e($product['name']) ?></h1>
            <p class="product-info__price"><?= e(money($product['price'])) ?></p>

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
                    <label class="sr-only" for="quantity">Quantity</label>
                    <div class="stepper">
                        <button type="button" class="stepper__btn" data-step="-1" aria-label="Decrease quantity">&minus;</button>
                        <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?= e((string) $maxQty) ?>" inputmode="numeric">
                        <button type="button" class="stepper__btn" data-step="1" aria-label="Increase quantity">+</button>
                    </div>
                    <button type="submit" class="btn btn--primary add-to-cart__button">Add to cart</button>
                </form>
            <?php endif; ?>

            <dl class="product-facts">
                <?php if (!empty($product['sku'])): ?>
                    <div><dt>Product code</dt><dd><?= e($product['sku']) ?></dd></div>
                <?php endif; ?>
                <div><dt>Category</dt><dd><a href="<?= e(url('products.php', ['category' => $product['category_slug']])) ?>"><?= e($product['category_name']) ?></a></dd></div>
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

<?php if ($related !== []): ?>
    <section class="section">
        <div class="container">
            <header class="section__header">
                <h2 class="section__title">You may also like</h2>
            </header>
            <div class="product-grid product-grid--four">
                <?php foreach ($related as $item): ?>
                    <?= View::partial('product-card', ['product' => $item]) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
