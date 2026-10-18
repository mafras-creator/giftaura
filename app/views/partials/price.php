<?php
/** Price with optional crossed-out normal price. Variables: $product, optional $size ('large'). */
$sale = sale_info($product);
?>
<p class="price<?= isset($size) ? ' price--' . e($size) : '' ?>">
    <span class="price__now"><?= e(money($product['price'])) ?></span>
    <?php if ($sale !== null): ?>
        <s class="price__was"><span class="sr-only">Normal price </span><?= e(money($sale['was'])) ?></s>
        <?php if (isset($size)): ?><span class="price__save">You save <?= e(money(CartService::fromCents(CartService::toCents($sale['was']) - CartService::toCents((string) $product['price'])))) ?> (<?= e((string) $sale['percent']) ?>%)</span><?php endif; ?>
    <?php endif; ?>
</p>
