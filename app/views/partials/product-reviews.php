<?php
/** Reviews section of the product page. Variables: $product, $summary, $reviews (paginated). */
$count = $summary['count'];
?>
<section class="section reviews" id="reviews" aria-labelledby="reviews-title">
    <div class="container">
        <div class="section-heading">
            <h2 class="section-heading__title" id="reviews-title">Customer reviews<?= $count > 0 ? ' (' . e((string) $count) . ')' : '' ?></h2>
        </div>
        <?php if ($count === 0): ?>
            <div class="reviews__empty">
                <p><strong>No reviews yet.</strong></p>
                <p class="muted">Customers who received this product can rate it from <em>My account → My reviews</em>.</p>
            </div>
        <?php else: ?>
            <div class="reviews__layout">
                <div class="reviews__summary">
                    <p class="reviews__average"><?= e(number_format($summary['average'], 1)) ?><span>/5</span></p>
                    <?= View::partial('stars', ['rating' => $summary['average'], 'size' => 'large']) ?>
                    <p class="muted">Based on <?= e((string) $count) ?> review<?= $count === 1 ? '' : 's' ?> from customers who received this product</p>
                    <ul class="reviews__bars">
                        <?php foreach ($summary['stars'] as $star => $n): ?>
                            <?php $pct = (int) round($count > 0 ? $n * 100 / $count : 0); ?>
                            <li>
                                <span class="reviews__bar-label"><?= e((string) $star) ?> ★</span>
                                <span class="reviews__bar" aria-hidden="true"><span class="reviews__bar-fill reviews__bar-fill--<?= e((string) (int) (round($pct / 5) * 5)) ?>"></span></span>
                                <span class="reviews__bar-count"><span class="sr-only"><?= e((string) $star) ?> stars: </span><?= e((string) $n) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div>
                    <ul class="review-items">
                        <?php foreach ($reviews['items'] as $r): ?>
                            <li class="review-item">
                                <span class="review-item__avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($r['reviewer_name'], 0, 1))) ?></span>
                                <div>
                                    <?= View::partial('stars', ['rating' => (int) $r['rating']]) ?>
                                    <p class="review-item__meta"><strong><?= e($r['reviewer_name']) ?></strong>
                                        <span class="review-item__verified">✓ Verified purchase</span>
                                        <span><?= e(date('j M Y', strtotime((string) $r['created_at']))) ?></span></p>
                                    <?php if ($r['comment'] !== null && $r['comment'] !== ''): ?>
                                        <p class="review-item__text"><?= e($r['comment']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($reviews['pages'] > 1): ?>
                        <nav class="reviews__pager" aria-label="More reviews">
                            <?php if ($reviews['page'] > 1): ?>
                                <a class="btn btn--ghost btn--small" href="<?= e(url('product.php', ['slug' => $product['slug'], 'rpage' => $reviews['page'] - 1])) ?>#reviews">&larr; Newer reviews</a>
                            <?php endif; ?>
                            <span class="muted">Page <?= e((string) $reviews['page']) ?> of <?= e((string) $reviews['pages']) ?></span>
                            <?php if ($reviews['page'] < $reviews['pages']): ?>
                                <a class="btn btn--ghost btn--small" href="<?= e(url('product.php', ['slug' => $product['slug'], 'rpage' => $reviews['page'] + 1])) ?>#reviews">Older reviews &rarr;</a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
