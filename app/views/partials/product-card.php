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
        <p class="product-card__price"><?= e(money($product['price'])) ?></p>
    </div>
</article>
