<?php
/** Edit the About page. Variables: $old, $errors. */
$f = AboutService::FIELDS;
$text = static function (string $key, array $old, array $errors, array $f, bool $multi = false, int $rows = 3): string {
    [$label, $max] = $f[$key];
    $value = (string) ($old[$key] ?? '');
    $input = $multi
        ? '<textarea id="' . e($key) . '" name="' . e($key) . '" rows="' . $rows . '" maxlength="' . $max . '"' . field_attrs($errors, $key) . '>' . e($value) . '</textarea>'
        : '<input type="text" id="' . e($key) . '" name="' . e($key) . '" value="' . e($value) . '" maxlength="' . $max . '"' . field_attrs($errors, $key) . '>';
    return '<div class="field"><label for="' . e($key) . '">' . e($label) . '</label>' . $input . field_error($errors, $key) . '</div>';
};
$photo = ImageUploader::url($old['founder_photo'] ?? null, 'people');
?>
<div class="page-actions">
    <p class="admin-lead">Everything on the public <strong>About us</strong> page. The numbers on the page (products, categories, ways to pay) are counted automatically.<?= empty($old['is_saved']) ? ' The texts below are a <strong>draft</strong> — please check them and press Save.' : '' ?></p>
    <a class="btn btn--ghost" href="<?= e(url('about.php')) ?>" target="_blank" rel="noopener">View the page</a>
</div>
<?php if ($errors !== []): ?>
    <div class="alert alert--error"><span>Please correct the highlighted fields.</span></div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/about.php')) ?>" enctype="multipart/form-data" class="admin-stack" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= e((string) ImageUploader::MAX_BYTES) ?>">

    <section class="admin-panel">
        <header class="admin-panel__header"><h2 class="admin-panel__title">Top of the page</h2></header>
        <div class="admin-form">
            <div class="field-row"><?= $text('headline', $old, $errors, $f) ?><?= $text('headline_highlight', $old, $errors, $f) ?></div>
            <?= $text('intro', $old, $errors, $f, true, 3) ?>
            <div class="field-row"><?= $text('founded_year', $old, $errors, $f) ?><div></div></div>
        </div>
    </section>

    <section class="admin-panel">
        <header class="admin-panel__header"><h2 class="admin-panel__title">Our story and founder</h2></header>
        <div class="admin-form">
            <?= $text('story_title', $old, $errors, $f) ?>
            <?= $text('story', $old, $errors, $f, true, 9) ?>
            <div class="field-row"><?= $text('founder_name', $old, $errors, $f) ?><?= $text('founder_role', $old, $errors, $f) ?></div>
            <?= $text('founder_quote', $old, $errors, $f, true, 2) ?>
            <div class="field">
                <label for="founder_photo">Founder photo <span class="optional">(optional)</span></label>
                <?php if ($photo !== null): ?>
                    <img class="category-thumb category-thumb--large" src="<?= e($photo) ?>" alt="" width="96" height="96">
                    <label class="check"><input type="checkbox" name="remove_photo" value="1"> Remove this photo</label>
                <?php endif; ?>
                <input type="file" id="founder_photo" name="founder_photo" accept="image/jpeg,image/png,image/webp" data-image-preview<?= field_attrs($errors, 'founder_photo') ?>>
                <?= field_error($errors, 'founder_photo') ?>
                <p class="hint">A square photo of the face works best (shown in a circle). Without a photo, the initials are shown.</p>
                <img class="upload-preview is-hidden" alt="" data-preview-target width="96" height="96">
            </div>
        </div>
    </section>

    <section class="admin-panel">
        <header class="admin-panel__header"><h2 class="admin-panel__title">Mission and vision</h2></header>
        <div class="admin-form">
            <div class="field-row"><?= $text('mission', $old, $errors, $f, true, 4) ?><?= $text('vision', $old, $errors, $f, true, 4) ?></div>
            <p class="hint">Leave both empty to hide this part.</p>
        </div>
    </section>

    <section class="admin-panel">
        <header class="admin-panel__header"><h2 class="admin-panel__title">Location</h2></header>
        <div class="admin-form">
            <div class="field-row"><?= $text('location', $old, $errors, $f) ?><?= $text('map_query', $old, $errors, $f) ?></div>
            <label class="check"><input type="checkbox" name="show_map" value="1"<?= !empty($old['show_map']) ? ' checked' : '' ?>> Show a Google map on the page</label>
            <p class="hint">Phone, e-mail, full address and opening hours come from <a href="<?= e(url('admin/settings.php')) ?>">Settings</a> (shown when filled in). For an exact map pin, type the shop's exact address in "Map search".</p>
        </div>
    </section>

    <div><button type="submit" class="btn btn--primary" data-loading-text="Saving…">Save About page</button></div>
</form>
