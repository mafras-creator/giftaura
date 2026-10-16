<?php
/** Address book. Variables: $user, $addresses, $editing (row|null), $errors, $old. */
$formAction = $editing ? 'update' : 'create';
?>
<section class="section">
    <div class="container account-layout">
        <div>
            <h1 class="page-title">My account</h1>
            <p class="page-lead">Hello, <?= e($user['full_name']) ?>.</p>
            <?= View::partial('account-nav') ?>
        </div>

        <div class="stack">
            <div class="panel">
                <h2 class="panel__title">Saved addresses</h2>
                <?php if ($addresses === []): ?>
                    <p class="empty-state">You have no saved addresses yet. Add one below, or save one during checkout.</p>
                <?php else: ?>
                    <div class="address-grid">
                        <?php foreach ($addresses as $a): ?>
                            <article class="address-card<?= (int) $a['is_default'] === 1 ? ' address-card--default' : '' ?>">
                                <h3 class="address-card__label">
                                    <?= e($a['label']) ?>
                                    <?php if ((int) $a['is_default'] === 1): ?><span class="badge badge--neutral">Default</span><?php endif; ?>
                                </h3>
                                <p>
                                    <?= e($a['recipient_name']) ?> &middot; <?= e($a['phone']) ?><br>
                                    <?= e($a['address_line1']) ?><?= $a['address_line2'] ? ', ' . e($a['address_line2']) : '' ?><br>
                                    <?= e($a['city']) ?>, <?= e($a['district']) ?> <?= e($a['postal_code'] ?? '') ?>
                                </p>
                                <div class="address-card__actions">
                                    <a class="btn btn--ghost btn--small" href="<?= e(url('account/addresses.php', ['edit' => $a['id']])) ?>">Edit</a>
                                    <?php if ((int) $a['is_default'] !== 1): ?>
                                        <form method="post" action="<?= e(url('account/addresses.php')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="default">
                                            <input type="hidden" name="address_id" value="<?= e((string) $a['id']) ?>">
                                            <button type="submit" class="btn btn--ghost btn--small">Make default</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="<?= e(url('account/addresses.php')) ?>" data-confirm="Delete this address?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="address_id" value="<?= e((string) $a['id']) ?>">
                                        <button type="submit" class="link-button link-button--danger">Delete</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="panel" id="address-form">
                <h2 class="panel__title"><?= $editing ? 'Edit address' : 'Add a new address' ?></h2>
                <form method="post" action="<?= e(url('account/addresses.php')) ?>" class="form" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= e($formAction) ?>">
                    <?php if ($editing): ?>
                        <input type="hidden" name="address_id" value="<?= e((string) $editing['id']) ?>">
                    <?php endif; ?>

                    <div class="form-row">
                        <div class="form-field">
                            <label for="label">Address name</label>
                            <input type="text" id="label" name="label" value="<?= e($old['label'] ?? '') ?>" maxlength="50" placeholder="Home, Office…"<?= field_attrs($errors, 'label') ?>>
                            <?= field_error($errors, 'label') ?>
                        </div>
                        <div class="form-field">
                            <label for="recipient_name">Recipient name</label>
                            <input type="text" id="recipient_name" name="recipient_name" value="<?= e($old['recipient_name'] ?? '') ?>" maxlength="100" required<?= field_attrs($errors, 'recipient_name') ?>>
                            <?= field_error($errors, 'recipient_name') ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-field">
                            <label for="phone">Recipient phone</label>
                            <input type="tel" id="phone" name="phone" value="<?= e($old['phone'] ?? '') ?>" maxlength="20" required placeholder="0771234567"<?= field_attrs($errors, 'phone') ?>>
                            <?= field_error($errors, 'phone') ?>
                        </div>
                        <div class="form-field">
                            <label for="address_line1">Address line 1</label>
                            <input type="text" id="address_line1" name="address_line1" value="<?= e($old['address_line1'] ?? '') ?>" maxlength="150" required<?= field_attrs($errors, 'address_line1') ?>>
                            <?= field_error($errors, 'address_line1') ?>
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="address_line2">Address line 2 <span class="optional">(optional)</span></label>
                        <input type="text" id="address_line2" name="address_line2" value="<?= e($old['address_line2'] ?? '') ?>" maxlength="150"<?= field_attrs($errors, 'address_line2') ?>>
                        <?= field_error($errors, 'address_line2') ?>
                    </div>
                    <div class="form-row form-row--three">
                        <div class="form-field">
                            <label for="city">City / town</label>
                            <input type="text" id="city" name="city" value="<?= e($old['city'] ?? '') ?>" maxlength="80" required<?= field_attrs($errors, 'city') ?>>
                            <?= field_error($errors, 'city') ?>
                        </div>
                        <div class="form-field">
                            <label for="district">District</label>
                            <select id="district" name="district" required<?= field_attrs($errors, 'district') ?>>
                                <option value="">Choose…</option>
                                <?php foreach (AddressService::DISTRICTS as $district): ?>
                                    <option value="<?= e($district) ?>"<?= ($old['district'] ?? '') === $district ? ' selected' : '' ?>><?= e($district) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error($errors, 'district') ?>
                        </div>
                        <div class="form-field">
                            <label for="postal_code">Postal code <span class="optional">(optional)</span></label>
                            <input type="text" id="postal_code" name="postal_code" value="<?= e($old['postal_code'] ?? '') ?>" maxlength="5" inputmode="numeric"<?= field_attrs($errors, 'postal_code') ?>>
                            <?= field_error($errors, 'postal_code') ?>
                        </div>
                    </div>
                    <label class="checkbox">
                        <input type="checkbox" name="is_default" value="1"<?= (int) ($editing['is_default'] ?? 0) === 1 ? ' checked' : '' ?>>
                        <span>Use as my default delivery address</span>
                    </label>
                    <div class="form-actions">
                        <button type="submit" class="btn btn--primary"><?= $editing ? 'Save changes' : 'Save address' ?></button>
                        <?php if ($editing): ?>
                            <a class="btn btn--ghost" href="<?= e(url('account/addresses.php')) ?>">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
