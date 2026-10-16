<?php
/** Store settings. Variables: $rows, $errors, $submitted. */
$groups = [
    'Store details'      => ['store_name', 'store_tagline', 'store_email', 'store_phone', 'store_whatsapp', 'store_address', 'store_hours'],
    'Delivery & ordering' => ['delivery_fee', 'free_delivery_threshold', 'max_quantity_per_item', 'default_low_stock_threshold'],
    'Payment methods'    => ['cod_enabled', 'bank_transfer_enabled'],
    'Bank account (shown to customers who pay by bank transfer)' => ['bank_name', 'bank_branch', 'bank_account_name', 'bank_account_number'],
];
$byKey = [];
foreach ($rows as $row) {
    $byKey[$row['setting_key']] = $row;
}
?>
<form method="post" action="<?= e(url('admin/settings.php')) ?>" class="settings-form" novalidate>
    <?= csrf_field() ?>
    <?php if ($errors !== []): ?>
        <div class="alert alert--error"><span>Some settings were not saved. Please correct the highlighted fields.</span></div>
    <?php endif; ?>

    <?php foreach ($groups as $groupLabel => $keys): ?>
        <section class="admin-panel">
            <header class="admin-panel__header"><h2 class="admin-panel__title"><?= e($groupLabel) ?></h2></header>
            <div class="panel-body admin-form settings-grid">
                <?php foreach ($keys as $key): ?>
                    <?php if (!isset($byKey[$key])) { continue; } $row = $byKey[$key]; $value = $submitted[$key] ?? $row['setting_value']; $fid = 'set-' . $key; ?>
                    <div class="field<?= $row['value_type'] === 'multiline' ? ' field--full' : '' ?>">
                        <?php if ($row['value_type'] === 'boolean'): ?>
                            <input type="hidden" name="settings[<?= e($key) ?>]" value="0">
                            <label class="check"><input type="checkbox" id="<?= e($fid) ?>" name="settings[<?= e($key) ?>]" value="1"<?= $value === '1' ? ' checked' : '' ?>> <?= e($row['label']) ?></label>
                        <?php else: ?>
                            <label for="<?= e($fid) ?>"><?= e($row['label']) ?></label>
                            <?php if ($row['value_type'] === 'multiline'): ?>
                                <textarea id="<?= e($fid) ?>" name="settings[<?= e($key) ?>]" rows="3" maxlength="1000"<?= field_attrs($errors, $key) ?>><?= e($value) ?></textarea>
                            <?php else: ?>
                                <input type="<?= $row['value_type'] === 'email' ? 'email' : 'text' ?>" id="<?= e($fid) ?>" name="settings[<?= e($key) ?>]"
                                       value="<?= e($value) ?>" maxlength="255"<?= in_array($row['value_type'], ['money', 'integer'], true) ? ' inputmode="decimal"' : '' ?><?= field_attrs($errors, $key) ?>>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?= field_error($errors, $key) ?>
                        <?php if (!empty($row['description'])): ?><p class="hint"><?= e($row['description']) ?></p><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <div class="sticky-actions">
        <button type="submit" class="btn btn--primary" data-loading-text="Saving…">Save settings</button>
        <span class="muted">Empty contact or bank fields are simply hidden on the website.</span>
    </div>
</form>
