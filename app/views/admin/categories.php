<?php
/** Admin categories. Variables: $categories, $parents, $editing, $parentForNew, $errors, $old. */
$formTitle = $editing ? 'Edit "' . $editing['name'] . '"'
    : ($parentForNew ? 'Add a subcategory to "' . $parentForNew['name'] . '"' : 'Add a main category');
?>
<div class="page-actions">
    <p class="admin-lead">Main categories (e.g. <em>Hampers</em>) appear as round pictures on the homepage. Subcategories (e.g. <em>Hampers › Fruit hampers</em>) appear inside them. Products can be put in either.</p>
    <a class="btn btn--primary" href="<?= e(url('admin/categories.php')) ?>#category-form">+ Add main category</a>
</div>

<div class="admin-columns admin-columns--wide-left">
    <section class="admin-panel">
        <header class="admin-panel__header">
            <h2 class="admin-panel__title">All categories</h2>
            <span class="muted"><?= e((string) count($categories)) ?> total</span>
        </header>
        <?php if ($categories === []): ?>
            <p class="empty-state">No categories yet. Add the first one with the form.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th scope="col">Name</th><th scope="col" class="num">Products</th><th scope="col" class="num">Order</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td><?= $c['parent_id'] !== null ? '<span class="muted" aria-hidden="true">↳ </span>' : '' ?><?php if (($thumb = ImageUploader::url($c['image_file'] ?? null, 'categories')) !== null): ?><img class="category-thumb" src="<?= e($thumb) ?>" alt="" width="40" height="40"><?php endif; ?><strong><?= e($c['name']) ?></strong>
                                <br><small class="muted"><?= $c['parent_id'] === null ? 'Main category' : 'Subcategory' ?> · /<?= e($c['slug']) ?><?php if ((int) $c['child_count'] > 0): ?> · <?= e((string) $c['child_count']) ?> subcategor<?= (int) $c['child_count'] === 1 ? 'y' : 'ies' ?><?php endif; ?></small></td>
                            <td class="num"><?= e((string) $c['product_count']) ?></td>
                            <td class="num"><?= e((string) $c['sort_order']) ?></td>
                            <td><?= (int) $c['is_active'] === 1 ? '<span class="badge badge--success">Active</span>' : '<span class="badge badge--neutral">Inactive</span>' ?></td>
                            <td class="actions">
                                <a class="btn btn--ghost btn--small" href="<?= e(url('admin/categories.php', ['edit' => $c['id']])) ?>#category-form">Edit</a>
                                <?php if ($c['parent_id'] === null): ?>
                                    <a class="btn btn--ghost btn--small" href="<?= e(url('admin/categories.php', ['parent' => $c['id']])) ?>#category-form">+ Subcategory</a>
                                <?php endif; ?>
                                <form method="post" action="<?= e(url('admin/categories.php')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $c['id']) ?>">
                                    <input type="hidden" name="action" value="<?= (int) $c['is_active'] === 1 ? 'deactivate' : 'activate' ?>">
                                    <button type="submit" class="btn btn--ghost btn--small"><?= (int) $c['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                                </form>
                                <?php if ((int) $c['product_count'] === 0 && (int) $c['child_count'] === 0): ?>
                                    <form method="post" action="<?= e(url('admin/categories.php')) ?>" data-confirm="Delete the category &quot;<?= e($c['name']) ?>&quot;?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e((string) $c['id']) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn--danger-ghost btn--small">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="panel-note">Deactivating a category hides it and all its products from the shop. Categories that contain products cannot be deleted.</p>
        <?php endif; ?>
    </section>

    <section class="admin-panel" id="category-form">
        <header class="admin-panel__header">
            <h2 class="admin-panel__title"><?= e($formTitle) ?></h2>
            <?php if ($editing || $parentForNew): ?><a class="btn btn--ghost btn--small" href="<?= e(url('admin/categories.php')) ?>">Cancel</a><?php endif; ?>
        </header>
        <form method="post" action="<?= e(url('admin/categories.php')) ?>" enctype="multipart/form-data" class="admin-form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= e((string) ImageUploader::MAX_BYTES) ?>">
            <?php if ($editing): ?><input type="hidden" name="id" value="<?= e((string) $editing['id']) ?>"><?php endif; ?>

            <div class="field">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="<?= e($old['name'] ?? '') ?>" maxlength="100" required<?= field_attrs($errors, 'name') ?>>
                <?= field_error($errors, 'name') ?>
            </div>
            <div class="field">
                <label for="slug">Web address (slug)</label>
                <input type="text" id="slug" name="slug" value="<?= e($old['slug'] ?? '') ?>" maxlength="120" placeholder="Created from the name if empty"<?= field_attrs($errors, 'slug') ?>>
                <?= field_error($errors, 'slug') ?>
                <p class="hint">Lowercase letters, numbers and hyphens, e.g. <code>flower-bouquets</code>.</p>
            </div>
            <div class="field">
                <label for="parent_id">Belongs to</label>
                <select id="parent_id" name="parent_id"<?= field_attrs($errors, 'parent_id') ?>>
                    <option value="">Nothing — this is a main category</option>
                    <?php foreach ($parents as $p): ?>
                        <option value="<?= e((string) $p['id']) ?>"<?= (string) ($old['parent_id'] ?? '') === (string) $p['id'] ? ' selected' : '' ?>><?= e($p['name']) ?><?= (int) $p['is_active'] === 1 ? '' : ' (inactive)' ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'parent_id') ?>
                <p class="hint">To make a subcategory, choose its main category, e.g. <em>Hampers</em> for "Fruit hampers".</p>
            </div>
            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3" maxlength="500"<?= field_attrs($errors, 'description') ?>><?= e($old['description'] ?? '') ?></textarea>
                <?= field_error($errors, 'description') ?>
            </div>
            <div class="field">
                <label for="image">Picture <span class="optional">(optional, round icon on the homepage)</span></label>
                <?php if ($editing && ($thumb = ImageUploader::url($editing['image_file'] ?? null, 'categories')) !== null): ?>
                    <img class="category-thumb category-thumb--large" src="<?= e($thumb) ?>" alt="" width="96" height="96">
                    <label class="check"><input type="checkbox" name="remove_image" value="1"> Remove this picture</label>
                <?php endif; ?>
                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" data-image-preview>
                <p class="hint">Square picture, e.g. 600 × 600 px, JPG/PNG/WebP up to 2 MB. Without a picture, a photo of one of the category's products is used.</p>
                <img class="upload-preview is-hidden" alt="" data-preview-target width="96" height="96">
            </div>
            <div class="field field--small">
                <label for="sort_order">Display order</label>
                <input type="number" id="sort_order" name="sort_order" value="<?= e((string) ($old['sort_order'] ?? '0')) ?>" step="1"<?= field_attrs($errors, 'sort_order') ?>>
                <?= field_error($errors, 'sort_order') ?>
                <p class="hint">Smaller numbers are shown first.</p>
            </div>
            <label class="check"><input type="checkbox" name="is_active" value="1"<?= (string) ($old['is_active'] ?? '1') === '1' ? ' checked' : '' ?>> Active (visible in the shop)</label>
            <button type="submit" class="btn btn--primary"<?= $editing ? '' : ' data-category-submit' ?>><?= $editing ? 'Save changes' : ($parentForNew ? 'Add subcategory' : 'Add main category') ?></button>
        </form>
    </section>
</div>

<?php
$popularSaved = PopularService::saved();
$popularOptions = CategoryAdminService::options();
?>
<section class="admin-panel popular-admin" id="popular">
    <header class="admin-panel__header">
        <h2 class="admin-panel__title">Popular categories (homepage)</h2>
        <a class="btn btn--ghost btn--small" href="<?= e(url()) ?>#main" target="_blank" rel="noopener">View homepage</a>
    </header>
    <form method="post" action="<?= e(url('admin/categories.php')) ?>" class="admin-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="popular">
        <p class="hint">Choose up to <?= e((string) PopularService::MAX) ?> categories for the coloured tiles near the bottom of the homepage, each with a short catchy line (e.g. "Say it with flowers!"). The tile picture is the category picture, or a product photo. <?= $popularSaved === [] ? 'Nothing is chosen yet, so the first ' . PopularService::MAX . ' main categories are shown with their number of gifts.' : '' ?></p>
        <?php for ($i = 0; $i < PopularService::MAX; $i++): $item = $popularSaved[$i] ?? ['id' => 0, 'tagline' => '']; ?>
            <div class="popular-admin__row">
                <span class="popular-admin__num"><?= e((string) ($i + 1)) ?></span>
                <label class="sr-only" for="popular-id-<?= $i ?>">Tile <?= $i + 1 ?> category</label>
                <select id="popular-id-<?= $i ?>" name="popular_id[]">
                    <option value="">— empty —</option>
                    <?php foreach ($popularOptions as $opt): ?>
                        <option value="<?= e((string) $opt['id']) ?>"<?= $opt['id'] === $item['id'] ? ' selected' : '' ?>><?= e($opt['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="sr-only" for="popular-line-<?= $i ?>">Tile <?= $i + 1 ?> short line</label>
                <input type="text" id="popular-line-<?= $i ?>" name="popular_tagline[]" value="<?= e($item['tagline']) ?>" maxlength="<?= e((string) PopularService::TAGLINE_MAX) ?>" placeholder="Short line, e.g. Say it with flowers!">
            </div>
        <?php endfor; ?>
        <p class="hint">Tiles 2 and 4 are shown wide on a computer. Leave the short line empty to show the number of gifts instead.</p>
        <div><button type="submit" class="btn btn--primary">Save popular categories</button></div>
    </form>
</section>
