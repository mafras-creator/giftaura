<?php
/** My account - reviews. Variables: $user, $items (ReviewService::reviewableProducts). */
$toReview = array_values(array_filter($items, static fn($i) => $i['review_id'] === null));
$done = array_values(array_filter($items, static fn($i) => $i['review_id'] !== null));
?>
<section class="section">
    <div class="container account-layout">
        <div>
            <h1 class="page-title">My account</h1>
            <p class="page-lead">Hello, <?= e($user['full_name']) ?>.</p>
            <?= View::partial('account-nav') ?>
        </div>

        <div class="account-stack">
            <div class="panel">
                <h2 class="panel__title">Waiting for your review <span class="muted">(<?= e((string) count($toReview)) ?>)</span></h2>
                <?php if ($items === []): ?>
                    <div class="empty-state">
                        <p><strong>Nothing to review yet.</strong></p>
                        <p>When an order has been delivered to you, its products appear here so you can rate them.</p>
                        <p><a class="btn btn--primary" href="<?= e(url('account/orders.php')) ?>">My orders</a></p>
                    </div>
                <?php elseif ($toReview === []): ?>
                    <p class="muted">You have reviewed every product delivered to you. Thank you!</p>
                <?php else: ?>
                    <ul class="review-todo">
                        <?php foreach ($toReview as $item): ?>
                            <li class="review-todo__item">
                                <img src="<?= e(product_image_url($item['image'])) ?>" alt="" width="64" height="64">
                                <div>
                                    <strong><?= e($item['name']) ?></strong>
                                    <span class="muted">Delivered order of <?= e(date('j M Y', strtotime((string) $item['ordered_at']))) ?></span>
                                </div>
                                <a class="btn btn--yellow btn--small" href="<?= e(url('account/review.php', ['product' => $item['product_id']])) ?>">Write a review</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <?php if ($done !== []): ?>
                <div class="panel">
                    <h2 class="panel__title">Your reviews <span class="muted">(<?= e((string) count($done)) ?>)</span></h2>
                    <ul class="review-list">
                        <?php foreach ($done as $item): ?>
                            <li class="review-list__item">
                                <div class="review-list__head">
                                    <?php if ((int) $item['is_active'] === 1): ?>
                                        <a href="<?= e(url('product.php', ['slug' => $item['slug']])) ?>#reviews"><strong><?= e($item['name']) ?></strong></a>
                                    <?php else: ?>
                                        <strong><?= e($item['name']) ?></strong>
                                    <?php endif; ?>
                                    <?= View::partial('stars', ['rating' => (int) $item['rating']]) ?>
                                    <?php if ((int) $item['is_visible'] !== 1): ?><span class="badge badge--neutral">Hidden by the store</span><?php endif; ?>
                                </div>
                                <?php if ($item['comment'] !== null && $item['comment'] !== ''): ?>
                                    <p class="review-list__text"><?= e($item['comment']) ?></p>
                                <?php endif; ?>
                                <p class="review-list__meta"><?= e(date('j M Y', strtotime((string) $item['reviewed_at']))) ?> ·
                                    <a href="<?= e(url('account/review.php', ['product' => $item['product_id']])) ?>">Edit or delete</a></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
