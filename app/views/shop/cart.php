<?php
/** Cart page. Variables: $cart (CartService::summary()), $maxQty, $user. */
?>
<div class="page-head">
    <div class="container">
        <h1 class="page-title">Your cart</h1>
        <p class="page-lead">Prices and stock are checked again every time this page loads.</p>
    </div>
</div>

<div class="container section--tight">
    <?php if ($cart['lines'] === []): ?>
        <div class="empty-state">
            <p><strong>Your cart is empty.</strong></p>
            <p><a class="btn btn--primary" href="<?= e(url('products.php')) ?>">Start shopping</a></p>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <section aria-label="Items in your cart">
                <ul class="cart-lines">
                    <?php foreach ($cart['lines'] as $line): ?>
                        <li class="cart-line<?= $line['problem'] ? ' cart-line--problem' : '' ?>">
                            <a class="cart-line__image" href="<?= e(url('product.php', ['slug' => $line['slug']])) ?>" tabindex="-1" aria-hidden="true">
                                <img src="<?= e(product_image_url($line['image'])) ?>" alt="" width="96" height="96">
                            </a>
                            <div class="cart-line__info">
                                <h2 class="cart-line__name">
                                    <?php if ($line['available']): ?>
                                        <a href="<?= e(url('product.php', ['slug' => $line['slug']])) ?>"><?= e($line['name']) ?></a>
                                    <?php else: ?>
                                        <?= e($line['name']) ?>
                                    <?php endif; ?>
                                </h2>
                                <p class="cart-line__price"><?= e(money($line['price'])) ?> each</p>
                                <?php if ($line['problem']): ?>
                                    <p class="cart-line__problem" role="alert"><?= e($line['problem']) ?></p>
                                <?php endif; ?>
                                <?php if ($line['customizable'] && $line['available']): ?>
                                    <?= View::partial('custom-note', ['text' => $line['message'], 'label' => $line['custom_label']]) ?>
                                    <details class="custom-edit"<?= $line['message'] === '' ? ' open' : '' ?>>
                                        <summary><?= $line['message'] === '' ? 'Add your personalisation' : 'Edit personalisation' ?></summary>
                                        <form method="post" action="<?= e(url('cart.php')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="message">
                                            <input type="hidden" name="product_id" value="<?= e((string) $line['product_id']) ?>">
                                            <label class="sr-only" for="msg-<?= e((string) $line['product_id']) ?>"><?= e($line['custom_label']) ?></label>
                                            <textarea id="msg-<?= e((string) $line['product_id']) ?>" name="customization_text" rows="2"
                                                      maxlength="<?= e((string) $line['custom_max']) ?>" placeholder="<?= e($line['custom_label']) ?>"><?= e($line['message']) ?></textarea>
                                            <button type="submit" class="btn btn--ghost btn--small">Save message</button>
                                        </form>
                                    </details>
                                <?php endif; ?>
                            </div>
                            <div class="cart-line__actions">
                                <?php if ($line['available'] && $line['stock_quantity'] > 0): ?>
                                    <form method="post" action="<?= e(url('cart.php')) ?>" class="qty-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="product_id" value="<?= e((string) $line['product_id']) ?>">
                                        <label class="sr-only" for="qty-<?= e((string) $line['product_id']) ?>">Quantity of <?= e($line['name']) ?></label>
                                        <input class="qty-input" type="number" id="qty-<?= e((string) $line['product_id']) ?>" name="quantity"
                                               value="<?= e((string) $line['quantity']) ?>" min="0"
                                               max="<?= e((string) min($maxQty, $line['stock_quantity'])) ?>" inputmode="numeric">
                                        <button type="submit" class="btn btn--ghost btn--small">Update</button>
                                    </form>
                                <?php else: ?>
                                    <p class="cart-line__qty">Qty <?= e((string) $line['quantity']) ?></p>
                                <?php endif; ?>
                                <form method="post" action="<?= e(url('cart.php')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="product_id" value="<?= e((string) $line['product_id']) ?>">
                                    <button type="submit" class="link-button">Remove</button>
                                </form>
                            </div>
                            <p class="cart-line__total"><?= e(money($line['line_total'])) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p><a href="<?= e(url('products.php')) ?>">&larr; Continue shopping</a></p>
            </section>

            <aside class="summary-card" aria-label="Order summary">
                <h2 class="summary-card__title">Order summary</h2>
                <dl class="totals">
                    <div><dt>Subtotal (<?= e((string) $cart['units']) ?> <?= $cart['units'] === 1 ? 'item' : 'items' ?>)</dt><dd><?= e(money($cart['subtotal'])) ?></dd></div>
                    <div><dt>Delivery</dt><dd><?= (float) $cart['delivery_fee'] > 0 ? e(money($cart['delivery_fee'])) : 'Free' ?></dd></div>
                    <div class="totals__grand"><dt>Total</dt><dd><?= e(money($cart['total'])) ?></dd></div>
                </dl>
                <?php
                $threshold = (float) setting('free_delivery_threshold', '0');
                if ($threshold > 0 && (float) $cart['delivery_fee'] > 0): ?>
                    <p class="summary-card__note">Free delivery on orders of <?= e(money($threshold)) ?> or more.</p>
                <?php endif; ?>

                <?php if ($cart['has_problems']): ?>
                    <div class="alert alert--error"><span>Please fix the items marked in red before checking out.</span></div>
                    <button type="button" class="btn btn--primary btn--block" disabled>Proceed to checkout</button>
                <?php elseif ($user !== null && Auth::isPanelRole($user['role'])): ?>
                    <div class="alert alert--info"><span>You are logged in as an administrator. Use a customer account to place orders.</span></div>
                <?php elseif ($user !== null && $user['email_verified_at'] === null): ?>
                    <div class="alert alert--warning"><span>Please verify your e-mail address before checking out. Open the link we sent to <?= e($user['email']) ?>.</span></div>
                    <button type="button" class="btn btn--primary btn--block" disabled>Proceed to checkout</button>
                <?php else: ?>
                    <a class="btn btn--primary btn--block" href="<?= e(url('checkout.php')) ?>">Proceed to checkout</a>
                    <?php if ($user === null): ?>
                        <p class="summary-card__note">You will be asked to log in or create an account. Your cart will be kept.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </aside>
        </div>
    <?php endif; ?>
</div>
