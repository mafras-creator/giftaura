<?php
/** Admin banners. Variables: $banners, $editing, $errors, $old. */
$stateBadge = ['Showing' => 'success', 'Scheduled' => 'warning', 'Ended' => 'neutral', 'Hidden' => 'neutral'];
?>
<p class="admin-lead">Banners are the large pictures that slide at the top of the homepage. Up to <?= e((string) BannerService::MAX_SHOWN) ?> active banners are shown, smallest display order first. With no active banner the homepage shows a simple welcome section instead.</p>

<div class="admin-columns admin-columns--wide-left">
    <section class="admin-panel">
        <header class="admin-panel__header">
            <h2 class="admin-panel__title">All banners</h2>
            <span class="muted"><?= e((string) count($banners)) ?> total</span>
        </header>
        <?php if ($banners === []): ?>
            <p class="empty-state">No banners yet. Add the first one with the form.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th scope="col">Banner</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($banners as $b): ?>
                        <?php $state = BannerService::state($b); $src = ImageUploader::url($b['image_file'], 'banners'); ?>
                        <tr>
                            <td>
                                <div class="banner-row">
                                    <?php if ($src !== null): ?><img class="banner-thumb" src="<?= e($src) ?>" alt="" width="160" height="50"><?php else: ?><span class="badge badge--danger">Picture missing</span><?php endif; ?>
                                    <div>
                                        <strong><?= e($b['title']) ?></strong><br>
                                        <small class="muted">Order <?= e((string) $b['sort_order']) ?> · <?= e($b['starts_on'] ?? 'from now') ?> to <?= e($b['ends_on'] ?? 'no end date') ?></small>
                                        <?php if ($b['link_url'] !== null): ?><br><small class="muted">Link: <?= e($b['link_url']) ?></small><?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge badge--<?= e($stateBadge[$state]) ?>"><?= e($state) ?></span></td>
                            <td class="actions">
                                <a class="btn btn--ghost btn--small" href="<?= e(url('admin/banners.php', ['edit' => $b['id']])) ?>">Edit</a>
                                <form method="post" action="<?= e(url('admin/banners.php')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $b['id']) ?>">
                                    <input type="hidden" name="action" value="<?= (int) $b['is_active'] === 1 ? 'hide' : 'show' ?>">
                                    <button type="submit" class="btn btn--ghost btn--small"><?= (int) $b['is_active'] === 1 ? 'Hide' : 'Show' ?></button>
                                </form>
                                <form method="post" action="<?= e(url('admin/banners.php')) ?>" data-confirm="Delete the banner &quot;<?= e($b['title']) ?>&quot;? The picture is deleted too.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $b['id']) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn--danger-ghost btn--small">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-panel" id="banner-form">
        <header class="admin-panel__header">
            <h2 class="admin-panel__title"><?= $editing ? 'Edit banner' : 'Add a banner' ?></h2>
            <?php if ($editing): ?><a class="btn btn--ghost btn--small" href="<?= e(url('admin/banners.php')) ?>">Cancel</a><?php endif; ?>
        </header>
        <form method="post" action="<?= e(url('admin/banners.php')) ?>" enctype="multipart/form-data" class="admin-form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= e((string) ImageUploader::MAX_BYTES) ?>">
            <?php if ($editing): ?><input type="hidden" name="id" value="<?= e((string) $editing['id']) ?>"><?php endif; ?>

            <div class="field">
                <label for="image"><?= $editing ? 'Replace the picture' : 'Picture' ?><?= $editing ? ' <span class="optional">(optional)</span>' : '' ?></label>
                <?php if ($editing && ($src = ImageUploader::url($editing['image_file'], 'banners')) !== null): ?>
                    <img class="banner-thumb banner-thumb--large" src="<?= e($src) ?>" alt="" width="320" height="100">
                <?php endif; ?>
                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" data-image-preview<?= $editing ? '' : ' required' ?><?= field_attrs($errors, 'image') ?>>
                <?= field_error($errors, 'image') ?>
                <p class="hint">Wide picture, recommended <strong>1920 × 600 px</strong> (at least <?= e((string) BannerService::MIN_WIDTH) ?> px wide), JPG/PNG/WebP up to 2 MB. Keep important content in the middle — the sides are cut off on phones.</p>
                <img class="upload-preview is-hidden" alt="" data-preview-target width="240" height="75">
            </div>
            <div class="field">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" value="<?= e($old['title'] ?? '') ?>" maxlength="120" required placeholder="e.g. Fresh flowers for every occasion"<?= field_attrs($errors, 'title') ?>>
                <?= field_error($errors, 'title') ?>
            </div>
            <div class="field">
                <label for="subtitle">Short text <span class="optional">(optional)</span></label>
                <input type="text" id="subtitle" name="subtitle" value="<?= e($old['subtitle'] ?? '') ?>" maxlength="200"<?= field_attrs($errors, 'subtitle') ?>>
                <?= field_error($errors, 'subtitle') ?>
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="button_text">Button text <span class="optional">(optional)</span></label>
                    <input type="text" id="button_text" name="button_text" value="<?= e($old['button_text'] ?? '') ?>" maxlength="40" placeholder="e.g. Shop now"<?= field_attrs($errors, 'button_text') ?>>
                    <?= field_error($errors, 'button_text') ?>
                </div>
                <div class="field">
                    <label for="link_url">Link <span class="optional">(optional)</span></label>
                    <input type="text" id="link_url" name="link_url" value="<?= e($old['link_url'] ?? '') ?>" maxlength="255" placeholder="products.php?category=flower-bouquets"<?= field_attrs($errors, 'link_url') ?>>
                    <?= field_error($errors, 'link_url') ?>
                </div>
            </div>
            <p class="hint">Tip: open the page you want in the shop and copy the part of the address after <code>/giftaura/</code>, e.g. <code>products.php?category=flower-bouquets</code>. Without a button, the whole banner is the link.</p>
            <div class="field-row">
                <div class="field">
                    <label for="starts_on">Show from <span class="optional">(optional)</span></label>
                    <input type="date" id="starts_on" name="starts_on" value="<?= e($old['starts_on'] ?? '') ?>"<?= field_attrs($errors, 'starts_on') ?>>
                    <?= field_error($errors, 'starts_on') ?>
                </div>
                <div class="field">
                    <label for="ends_on">Show until <span class="optional">(optional)</span></label>
                    <input type="date" id="ends_on" name="ends_on" value="<?= e($old['ends_on'] ?? '') ?>"<?= field_attrs($errors, 'ends_on') ?>>
                    <?= field_error($errors, 'ends_on') ?>
                </div>
            </div>
            <div class="field field--small">
                <label for="sort_order">Display order</label>
                <input type="number" id="sort_order" name="sort_order" value="<?= e((string) ($old['sort_order'] ?? '0')) ?>" step="1"<?= field_attrs($errors, 'sort_order') ?>>
                <?= field_error($errors, 'sort_order') ?>
            </div>
            <label class="check check--top"><input type="checkbox" name="show_text" value="1"<?= (string) ($old['show_text'] ?? '1') === '1' ? ' checked' : '' ?>> <span>Show the title, text and button on the picture<br><span class="optional">Untick this if the picture already contains its own text.</span></span></label>
            <label class="check"><input type="checkbox" name="is_active" value="1"<?= (string) ($old['is_active'] ?? '1') === '1' ? ' checked' : '' ?>> Active</label>
            <button type="submit" class="btn btn--primary" data-loading-text="Saving…"><?= $editing ? 'Save changes' : 'Add banner' ?></button>
        </form>
    </section>
</div>
