<?php
/**
 * Checkout form. Variables: $user, $cart, $addresses, $methods, $errors, $general, $old.
 */
$useNew = ($old['address_id'] ?? 'new') === 'new' || $addresses === [];
?>
<div class="page-head">
    <div class="container">
        <nav class="breadcrumbs" aria-label="Breadcrumb">
            <a href="<?= e(url('cart.php')) ?>">Cart</a><span aria-hidden="true">/</span><span aria-current="page">Checkout</span>
        </nav>
        <h1 class="page-title">Checkout</h1>
    </div>
</div>

<div class="container section--tight">
    <?php if ($general): ?>
        <div class="alert alert--error" role="alert"><span><?= e($general) ?></span></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('checkout.php')) ?>" class="checkout-layout" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="cart_fingerprint" value="<?= e($cart['fingerprint']) ?>">

        <div class="checkout-steps">
            <section class="panel">
                <h2 class="panel__title"><span class="step-number">1</span> Your contact details</h2>
                <div class="form-row">
                    <div class="form-field">
                        <label for="customer_name">Your name</label>
                        <input type="text" id="customer_name" name="customer_name" value="<?= e($old['customer_name'] ?? '') ?>"
                               required maxlength="100" autocomplete="name"<?= field_attrs($errors, 'customer_name') ?>>
                        <?= field_error($errors, 'customer_name') ?>
                    </div>
                    <div class="form-field">
                        <label for="customer_phone">Your mobile number</label>
                        <input type="tel" id="customer_phone" name="customer_phone" value="<?= e($old['customer_phone'] ?? '') ?>"
                               required maxlength="20" autocomplete="tel" placeholder="0771234567"<?= field_attrs($errors, 'customer_phone') ?>>
                        <?= field_error($errors, 'customer_phone') ?>
                    </div>
                </div>
                <p class="form-hint">Order updates are shown in your account (<?= e($user['email']) ?>).</p>
            </section>

            <section class="panel">
                <h2 class="panel__title"><span class="step-number">2</span> Delivery address</h2>

                <?php if ($addresses !== []): ?>
                    <fieldset class="choice-list">
                        <legend class="sr-only">Choose a delivery address</legend>
                        <?php foreach ($addresses as $address): ?>
                            <label class="choice">
                                <input type="radio" name="address_id" value="<?= e((string) $address['id']) ?>" data-address-choice
                                    <?= (string) ($old['address_id'] ?? '') === (string) $address['id'] ? 'checked' : '' ?>>
                                <span class="choice__body">
                                    <strong><?= e($address['label']) ?><?= (int) $address['is_default'] === 1 ? ' (default)' : '' ?></strong>
                                    <span><?= e($address['recipient_name']) ?>, <?= e($address['phone']) ?></span>
                                    <span><?= e($address['address_line1']) ?><?= $address['address_line2'] ? ', ' . e($address['address_line2']) : '' ?>,
                                          <?= e($address['city']) ?>, <?= e($address['district']) ?> <?= e($address['postal_code'] ?? '') ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                        <label class="choice">
                            <input type="radio" name="address_id" value="new" data-address-choice<?= $useNew ? ' checked' : '' ?>>
                            <span class="choice__body"><strong>Deliver to a new address</strong></span>
                        </label>
                    </fieldset>
                    <?= field_error($errors, 'address_id') ?>
                <?php else: ?>
                    <input type="hidden" name="address_id" value="new">
                <?php endif; ?>

                <div class="new-address<?= $useNew ? '' : ' is-hidden' ?>" data-new-address>
                    <div class="form-row">
                        <div class="form-field">
                            <label for="recipient_name">Recipient name</label>
                            <input type="text" id="recipient_name" name="recipient_name" value="<?= e($old['recipient_name'] ?? '') ?>"
                                   maxlength="100"<?= field_attrs($errors, 'recipient_name') ?>>
                            <?= field_error($errors, 'recipient_name') ?>
                        </div>
                        <div class="form-field">
                            <label for="phone">Recipient phone</label>
                            <input type="tel" id="phone" name="phone" value="<?= e($old['phone'] ?? '') ?>" maxlength="20"
                                   placeholder="0771234567"<?= field_attrs($errors, 'phone') ?>>
                            <?= field_error($errors, 'phone') ?>
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="address_line1">Address line 1</label>
                        <input type="text" id="address_line1" name="address_line1" value="<?= e($old['address_line1'] ?? '') ?>"
                               maxlength="150" autocomplete="address-line1" placeholder="House number and street"<?= field_attrs($errors, 'address_line1') ?>>
                        <?= field_error($errors, 'address_line1') ?>
                    </div>
                    <div class="form-field">
                        <label for="address_line2">Address line 2 <span class="optional">(optional)</span></label>
                        <input type="text" id="address_line2" name="address_line2" value="<?= e($old['address_line2'] ?? '') ?>"
                               maxlength="150" autocomplete="address-line2"<?= field_attrs($errors, 'address_line2') ?>>
                        <?= field_error($errors, 'address_line2') ?>
                    </div>
                    <div class="form-row form-row--three">
                        <div class="form-field">
                            <label for="city">City / town</label>
                            <input type="text" id="city" name="city" value="<?= e($old['city'] ?? '') ?>" maxlength="80"
                                   autocomplete="address-level2"<?= field_attrs($errors, 'city') ?>>
                            <?= field_error($errors, 'city') ?>
                        </div>
                        <div class="form-field">
                            <label for="district">District</label>
                            <select id="district" name="district"<?= field_attrs($errors, 'district') ?>>
                                <option value="">Choose…</option>
                                <?php foreach (AddressService::DISTRICTS as $district): ?>
                                    <option value="<?= e($district) ?>"<?= ($old['district'] ?? '') === $district ? ' selected' : '' ?>><?= e($district) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error($errors, 'district') ?>
                        </div>
                        <div class="form-field">
                            <label for="postal_code">Postal code <span class="optional">(optional)</span></label>
                            <input type="text" id="postal_code" name="postal_code" value="<?= e($old['postal_code'] ?? '') ?>"
                                   maxlength="5" inputmode="numeric" autocomplete="postal-code"<?= field_attrs($errors, 'postal_code') ?>>
                            <?= field_error($errors, 'postal_code') ?>
                        </div>
                    </div>
                    <label class="checkbox">
                        <input type="checkbox" name="save_address" value="1"<?= ($old['save_address'] ?? '') === '1' ? ' checked' : '' ?>>
                        <span>Save this address to my account</span>
                    </label>
                </div>

                <div class="form-field form-field--spaced">
                    <label for="delivery_notes">Delivery notes or gift message <span class="optional">(optional)</span></label>
                    <textarea id="delivery_notes" name="delivery_notes" rows="3" maxlength="500"
                              placeholder="e.g. Happy birthday! Please call before delivery."<?= field_attrs($errors, 'delivery_notes') ?>><?= e($old['delivery_notes'] ?? '') ?></textarea>
                    <?= field_error($errors, 'delivery_notes') ?>
                </div>
            </section>

            <section class="panel">
                <h2 class="panel__title"><span class="step-number">3</span> Payment</h2>
                <?php if ($methods === []): ?>
                    <div class="alert alert--error"><span>Online ordering is temporarily unavailable because no payment method is enabled. Please contact the store.</span></div>
                <?php else: ?>
                    <fieldset class="choice-list">
                        <legend class="sr-only">Payment method</legend>
                        <?php foreach ($methods as $key => $label): ?>
                            <label class="choice">
                                <input type="radio" name="payment_method" value="<?= e($key) ?>"<?= ($old['payment_method'] ?? '') === $key ? ' checked' : '' ?>>
                                <span class="choice__body">
                                    <strong><?= e($label) ?></strong>
                                    <?php if ($key === 'cod'): ?>
                                        <span>Pay in cash when your order is delivered.</span>
                                    <?php else: ?>
                                        <span>Transfer the total to our bank account. We prepare your order once the payment is confirmed. Bank details are shown after you place the order.</span>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <?= field_error($errors, 'payment_method') ?>
                    <p class="form-hint">No card details are collected on this website.</p>
                <?php endif; ?>
            </section>
        </div>

        <aside class="summary-card summary-card--sticky" aria-label="Order summary">
            <h2 class="summary-card__title">Your order</h2>
            <ul class="summary-lines">
                <?php foreach ($cart['lines'] as $line): ?>
                    <li>
                        <span><?= e($line['name']) ?> <span class="muted">&times; <?= e((string) $line['quantity']) ?></span></span>
                        <span><?= e(money($line['line_total'])) ?></span>
                        <?php if ($line['customizable']): ?><?= View::partial('custom-note', ['text' => $line['message'], 'label' => $line['custom_label']]) ?><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <dl class="totals">
                <div><dt>Subtotal</dt><dd><?= e(money($cart['subtotal'])) ?></dd></div>
                <div><dt>Delivery</dt><dd><?= (float) $cart['delivery_fee'] > 0 ? e(money($cart['delivery_fee'])) : 'Free' ?></dd></div>
                <div><dt>Discount</dt><dd><?= e(money(0)) ?></dd></div>
                <div class="totals__grand"><dt>Total to pay</dt><dd><?= e(money($cart['total'])) ?></dd></div>
            </dl>
            <button type="submit" class="btn btn--primary btn--block" data-loading-text="Placing your order…"<?= $methods === [] ? ' disabled' : '' ?>>Place order</button>
            <p class="summary-card__note">By placing the order you agree to pay <?= e(money($cart['total'])) ?> using the method chosen above.</p>
            <a class="summary-card__link" href="<?= e(url('cart.php')) ?>">Edit cart</a>
        </aside>
    </form>
</div>
