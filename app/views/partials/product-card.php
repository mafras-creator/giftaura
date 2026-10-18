<?php
/** Product card. Variables: $product (row from ProductService). */
$status = stock_status($product);
$link = url('product.php', ['slug' => $product['slug']]);
$sale = sale_info($product);
?>
<article class="product-card<?= $status['key'] === 'out_of_stock' ? ' product-card--soldout' : '' ?>">
    <div class="product-card__media-wrap">
        <a class="product-card__media" href="<?= e($link) ?>" tabindex="-1" aria-hidden="true">
            <img src="<?= e(product_image_url($product['image'] ?? null)) ?>" alt="" loading="lazy" width="400" height="400">
            <?php if ($sale !== null): ?><span class="sale-tag">-<?= e((string) $sale['percent']) ?>%</span><?php endif; ?>
            <?php if ($status['key'] !== 'in_stock'): ?>
                <span class="stock-tag stock-tag--<?= e($status['key']) ?>"><?= e($status['label']) ?></span>
            <?php endif; ?>
        </a>
        <?= View::partial('wishlist-button', ['product' => $product]) ?>
    </div>
    <div class="product-card__body">
        <h3 class="product-card__name"><a href="<?= e($link) ?>"><?= e($product['name']) ?></a></h3>
        <?= View::partial('price', ['product' => $product]) ?>
        <?php if ((int) ($product['rating_count'] ?? 0) > 0): ?>
            <p class="card-rating"><?= View::partial('stars', ['rating' => (float) $product['rating_avg'], 'size' => 'small']) ?>
                <span class="card-rating__value"><?= e(number_format((float) $product['rating_avg'], 1)) ?></span>
                <span class="card-rating__count">(<?= e((string) (int) $product['rating_count']) ?>)</span></p>
        <?php endif; ?>
        <?php $customizable = (int) ($product['is_customizable'] ?? 0) === 1; ?>
        <?php if ($customizable): ?>
            <p class="personalisable"><svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg> Personalisable</p>
        <?php endif; ?>
        <?php if ($status['key'] !== 'out_of_stock' && $customizable): ?>
            <a class="btn btn--yellow btn--small btn--block" href="<?= e($link) ?>#personalise" aria-label="Personalise <?= e($product['name']) ?>">
                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16z" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/></svg>
                Personalise
            </a>
        <?php elseif ($status['key'] !== 'out_of_stock'): ?>
            <form method="post" action="<?= e(url('cart.php')) ?>" class="product-card__form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= e((string) $product['id']) ?>">
                <input type="hidden" name="quantity" value="1">
                <input type="hidden" name="return" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
                <button type="submit" class="btn btn--yellow btn--small btn--block" aria-label="Add <?= e($product['name']) ?> to cart">
                    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M6 6h15l-1.5 9h-12z" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/><path d="M6 6 5 3H2" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><circle cx="9" cy="20" r="1.6" fill="currentColor"/><circle cx="18" cy="20" r="1.6" fill="currentColor"/></svg>
                    Add to cart
                </button>
            </form>
        <?php else: ?>
            <p class="product-card__soldout">Out of stock</p>
        <?php endif; ?>
    </div>
</article>
