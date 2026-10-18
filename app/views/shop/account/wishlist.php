<?php
/** My account - wishlist. Variables: $user, $items. */
?>
<section class="section">
    <div class="container account-layout">
        <div>
            <h1 class="page-title">My account</h1>
            <p class="page-lead">Hello, <?= e($user['full_name']) ?>.</p>
            <?= View::partial('account-nav') ?>
        </div>

        <div class="panel">
            <h2 class="panel__title">My wishlist <span class="muted">(<?= e((string) count($items)) ?>)</span></h2>
            <?php if ($items === []): ?>
                <div class="empty-state">
                    <p><strong>Your wishlist is empty.</strong></p>
                    <p>Press the heart on any product picture to save it here for later.</p>
                    <p><a class="btn btn--yellow" href="<?= e(url('products.php')) ?>">Browse products</a></p>
                </div>
            <?php else: ?>
                <div class="product-grid product-grid--wishlist">
                    <?php foreach ($items as $product): ?>
                        <?= View::partial('product-card', ['product' => $product]) ?>
                    <?php endforeach; ?>
                </div>
                <p class="muted">Prices and stock are always the current ones. Press the heart again to remove a product.</p>
            <?php endif; ?>
        </div>
    </div>
</section>
