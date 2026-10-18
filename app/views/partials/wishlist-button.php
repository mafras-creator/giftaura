<?php
/** Heart button. Variables: $product (id, name), optional $extraClass. */
$saved = WishlistService::has((int) $product['id']);
?>
<form method="post" action="<?= e(url('wishlist.php')) ?>" class="wish<?= isset($extraClass) ? ' ' . e($extraClass) : '' ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="product_id" value="<?= e((string) $product['id']) ?>">
    <input type="hidden" name="return" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
    <button type="submit" class="wish__button<?= $saved ? ' is-saved' : '' ?>" aria-pressed="<?= $saved ? 'true' : 'false' ?>"
            aria-label="<?= e(($saved ? 'Remove ' : 'Save ') . $product['name'] . ($saved ? ' from' : ' to') . ' your wishlist') ?>"
            title="<?= $saved ? 'Remove from wishlist' : 'Save to wishlist' ?>">
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-9.3-9.2C1.5 8.1 3.6 4.5 7.2 4.5c2 0 3.6 1.1 4.8 2.8 1.2-1.7 2.8-2.8 4.8-2.8 3.6 0 5.7 3.6 4.5 6.8-1.8 4.6-9.3 9.2-9.3 9.2z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
    </button>
</form>
