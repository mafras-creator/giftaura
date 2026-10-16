<?php
/** Product card. Variables: $product (row from ProductService). */
$status = stock_status($product);
$link = url('product.php', ['slug' => $product['slug']]);
?>
<article class="product-card<?= $status['key'] === 'out_of_stock' ? ' product-card--soldout' : '' ?>">
    <a class="product-card__media" href="<?= e($link) ?>" tabindex="-1" aria-hidden="true">
        <img src="<?= e(product_image_url($product['image'] ?? null)) ?>" alt="" loading="lazy" width="400" height="400">
        <?php if ($status['key'] !== 'in_stock'): ?>
            <span class="stock-tag stock-tag--<?= e($status['key']) ?>"><?= e($status['label']) ?></span>
        <?php endif; ?>
    </a>
    <div class="product-card__body">
        <p class="product-card__category"><?= e($product['category_name']) ?></p>
        <h3 class="product-card__name"><a href="<?= e($link) ?>"><?= e($product['name']) ?></a></h3>
        <div class="product-card__foot">
            <p class="product-card__price"><?= e(money($product['price'])) ?></p>
            <?php if ($status['key'] !== 'out_of_stock'): ?>
                <form method="post" action="<?= e(url('cart.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= e((string) $product['id']) ?>">
                    <input type="hidden" name="quantity" value="1">
                    <input type="hidden" name="return" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
                    <button type="submit" class="btn btn--small btn--outline" aria-label="Add <?= e($product['name']) ?> to cart">Add</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</article>
