<?php
/** Write or edit a review. Variables: $user, $product, $review (or null), $old, $errors. */
$current = (string) ($old['rating'] ?? '');
?>
<section class="section">
    <div class="container account-layout">
        <div>
            <h1 class="page-title">My account</h1>
            <p class="page-lead">Hello, <?= e($user['full_name']) ?>.</p>
            <?= View::partial('account-nav') ?>
        </div>

        <div class="panel">
            <p><a href="<?= e(url('account/reviews.php')) ?>">&larr; My reviews</a></p>
            <div class="review-product">
                <img src="<?= e(product_image_url($product['image'])) ?>" alt="" width="72" height="72">
                <div>
                    <h2 class="panel__title"><?= $review === null ? 'Write a review' : 'Edit your review' ?></h2>
                    <p class="muted"><?= e($product['name']) ?></p>
                </div>
            </div>
            <?php if (isset($errors['general'])): ?>
                <div class="alert alert--error"><span><?= e($errors['general']) ?></span></div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('account/review.php', ['product' => $product['id']])) ?>" class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <fieldset class="rating-input<?= isset($errors['rating']) ? ' has-error' : '' ?>">
                    <legend>Your rating</legend>
                    <div class="rating-input__stars">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="rating-<?= $i ?>" name="rating" value="<?= $i ?>"<?= $current === (string) $i ? ' checked' : '' ?> required>
                            <label for="rating-<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>"><span class="sr-only"><?= $i ?> star<?= $i > 1 ? 's' : '' ?></span><span aria-hidden="true">★</span></label>
                        <?php endfor; ?>
                    </div>
                    <p class="hint">5 = excellent, 1 = very poor.</p>
                    <?= field_error($errors, 'rating') ?>
                </fieldset>

                <div class="field">
                    <label for="comment">Your review <span class="optional">(optional)</span></label>
                    <textarea id="comment" name="comment" rows="5" maxlength="<?= e((string) ReviewService::COMMENT_MAX) ?>" data-char-count
                              placeholder="What did you like? How was the packing and delivery?"<?= field_attrs($errors, 'comment') ?>><?= e((string) ($old['comment'] ?? '')) ?></textarea>
                    <p class="personalise__meta"><span>Shown under the product with your first name and the first letter of your last name (<?= e(ReviewService::displayName($user['full_name'])) ?>).</span><span data-char-counter aria-live="polite"></span></p>
                    <?= field_error($errors, 'comment') ?>
                </div>
                <button type="submit" class="btn btn--primary"><?= $review === null ? 'Post review' : 'Save changes' ?></button>
            </form>

            <?php if ($review !== null): ?>
                <form method="post" action="<?= e(url('account/review.php', ['product' => $product['id']])) ?>" class="review-delete" data-confirm="Delete your review?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="link-button link-button--danger">Delete my review</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
