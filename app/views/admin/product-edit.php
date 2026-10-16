<?php
/**
 * Add / edit product. Variables: $product (null for new), $old, $errors,
 * $categories, $images, $movements.
 */
$isNew = $product === null;
$formUrl = $isNew ? url('admin/product-edit.php') : url('admin/product-edit.php', ['id' => $product['id']]);
?>
<p><a href="<?= e(url('admin/products.php')) ?>">&larr; All products</a></p>

<div class="admin-columns admin-columns--wide-left">
    <section class="admin-panel">
        <header class="admin-panel__header">
            <h2 class="admin-panel__title"><?= $isNew ? 'New product' : e($product['name']) ?></h2>
            <?php if (!$isNew && (int) $product['is_active'] === 1): ?>
                <a class="btn btn--ghost btn--small" href="<?= e(url('product.php', ['slug' => $product['slug']])) ?>" target="_blank" rel="noopener">View in shop</a>
            <?php endif; ?>
        </header>
        <?php if ($errors !== []): ?>
            <div class="alert alert--error panel-alert"><span>Please correct the highlighted fields.</span></div>
        <?php endif; ?>
        <form method="post" action="<?= e($formUrl) ?>" class="admin-form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">

            <div class="field">
                <label for="name">Product name</label>
                <input type="text" id="name" name="name" value="<?= e($old['name'] ?? '') ?>" maxlength="150" required<?= field_attrs($errors, 'name') ?>>
                <?= field_error($errors, 'name') ?>
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id" required<?= field_attrs($errors, 'category_id') ?>>
                        <option value="">Choose…</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= e((string) $c['id']) ?>"<?= (string) ($old['category_id'] ?? '') === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'category_id') ?>
                </div>
                <div class="field">
                    <label for="price">Price (LKR)</label>
                    <input type="text" id="price" name="price" value="<?= e((string) ($old['price'] ?? '')) ?>" inputmode="decimal" placeholder="e.g. 4500.00" required<?= field_attrs($errors, 'price') ?>>
                    <?= field_error($errors, 'price') ?>
                </div>
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="sku">SKU / product code <span class="optional">(optional)</span></label>
                    <input type="text" id="sku" name="sku" value="<?= e($old['sku'] ?? '') ?>" maxlength="40"<?= field_attrs($errors, 'sku') ?>>
                    <?= field_error($errors, 'sku') ?>
                </div>
                <div class="field">
                    <label for="slug">Web address (slug)</label>
                    <input type="text" id="slug" name="slug" value="<?= e($old['slug'] ?? '') ?>" maxlength="170" placeholder="Created from the name if empty"<?= field_attrs($errors, 'slug') ?>>
                    <?= field_error($errors, 'slug') ?>
                </div>
            </div>
            <div class="field">
                <label for="short_description">Short description <span class="optional">(shown under the name)</span></label>
                <input type="text" id="short_description" name="short_description" value="<?= e($old['short_description'] ?? '') ?>" maxlength="255"<?= field_attrs($errors, 'short_description') ?>>
                <?= field_error($errors, 'short_description') ?>
            </div>
            <div class="field">
                <label for="description">Full description</label>
                <textarea id="description" name="description" rows="6" maxlength="5000"<?= field_attrs($errors, 'description') ?>><?= e($old['description'] ?? '') ?></textarea>
                <?= field_error($errors, 'description') ?>
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="low_stock_threshold">Low-stock alert level</label>
                    <input type="number" id="low_stock_threshold" name="low_stock_threshold" value="<?= e((string) ($old['low_stock_threshold'] ?? '5')) ?>" min="0" step="1"<?= field_attrs($errors, 'low_stock_threshold') ?>>
                    <?= field_error($errors, 'low_stock_threshold') ?>
                    <p class="hint">An alert is shown when stock is at or below this number.</p>
                </div>
                <?php if ($isNew): ?>
                    <div class="field">
                        <label for="initial_stock">Opening stock</label>
                        <input type="number" id="initial_stock" name="initial_stock" value="<?= e((string) ($old['initial_stock'] ?? '0')) ?>" min="0" step="1"<?= field_attrs($errors, 'initial_stock') ?>>
                        <?= field_error($errors, 'initial_stock') ?>
                        <p class="hint">Later changes are made in the Stock box, so every change is recorded.</p>
                    </div>
                <?php else: ?>
                    <div class="field">
                        <span class="label">Current stock</span>
                        <p class="big-number"><?= e((string) $product['stock_quantity']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <label class="check"><input type="checkbox" name="is_active" value="1"<?= (string) ($old['is_active'] ?? '1') === '1' ? ' checked' : '' ?>> Active (visible in the shop)</label>
            <label class="check"><input type="checkbox" name="is_featured" value="1"<?= (string) ($old['is_featured'] ?? '0') === '1' ? ' checked' : '' ?>> Featured on the homepage</label>
            <button type="submit" class="btn btn--primary"><?= $isNew ? 'Create product' : 'Save changes' ?></button>
        </form>
    </section>

    <div class="admin-stack">
        <?php if ($isNew): ?>
            <section class="admin-panel">
                <p class="empty-state">Create the product first, then you can upload images and manage stock here.</p>
            </section>
        <?php else: ?>
            <section class="admin-panel">
                <header class="admin-panel__header"><h2 class="admin-panel__title">Images</h2><span class="muted"><?= e((string) count($images)) ?> / <?= e((string) ProductAdminService::MAX_IMAGES) ?></span></header>
                <div class="panel-body">
                    <?php if ($images === []): ?>
                        <p class="muted">No images yet. The shop shows a placeholder.</p>
                    <?php else: ?>
                        <ul class="image-grid">
                            <?php foreach ($images as $img): ?>
                                <li class="image-tile<?= (int) $img['is_primary'] === 1 ? ' image-tile--primary' : '' ?>">
                                    <img src="<?= e(product_image_url($img['file_name'])) ?>" alt="<?= e($img['alt_text'] ?? '') ?>" width="160" height="160">
                                    <?php if ((int) $img['is_primary'] === 1): ?><span class="badge badge--success image-tile__badge">Main image</span><?php endif; ?>
                                    <form method="post" action="<?= e($formUrl) ?>" class="image-tile__alt">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="alt">
                                        <input type="hidden" name="image_id" value="<?= e((string) $img['id']) ?>">
                                        <label class="sr-only" for="alt-<?= e((string) $img['id']) ?>">Image description</label>
                                        <input type="text" id="alt-<?= e((string) $img['id']) ?>" name="alt_text" value="<?= e($img['alt_text'] ?? '') ?>" maxlength="150" placeholder="Description">
                                        <button type="submit" class="btn btn--ghost btn--small">Save</button>
                                    </form>
                                    <div class="image-tile__actions">
                                        <?php if ((int) $img['is_primary'] !== 1): ?>
                                            <form method="post" action="<?= e($formUrl) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="primary">
                                                <input type="hidden" name="image_id" value="<?= e((string) $img['id']) ?>">
                                                <button type="submit" class="btn btn--ghost btn--small">Make main</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" action="<?= e($formUrl) ?>" data-confirm="Delete this image?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_image">
                                            <input type="hidden" name="image_id" value="<?= e((string) $img['id']) ?>">
                                            <button type="submit" class="btn btn--danger-ghost btn--small">Delete</button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (count($images) < ProductAdminService::MAX_IMAGES): ?>
                        <form method="post" action="<?= e($formUrl) ?>" enctype="multipart/form-data" class="admin-form upload-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="upload">
                            <input type="hidden" name="MAX_FILE_SIZE" value="<?= e((string) ImageUploader::MAX_BYTES) ?>">
                            <div class="field">
                                <label for="image">Upload an image</label>
                                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" required data-image-preview>
                                <p class="hint">JPG, PNG or WebP, up to 2 MB, at least 100 × 100 px. Large images are resized to 1600 px.</p>
                                <img class="upload-preview is-hidden" alt="" data-preview-target width="120" height="120">
                            </div>
                            <div class="field">
                                <label for="alt_text">Image description <span class="optional">(for screen readers)</span></label>
                                <input type="text" id="alt_text" name="alt_text" maxlength="150" placeholder="e.g. Red roses wrapped in pink paper">
                            </div>
                            <button type="submit" class="btn btn--primary btn--small" data-loading-text="Uploading…">Upload</button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>

            <section class="admin-panel">
                <header class="admin-panel__header"><h2 class="admin-panel__title">Stock</h2><span class="big-number big-number--inline"><?= e((string) $product['stock_quantity']) ?> in stock</span></header>
                <div class="panel-body">
                    <form method="post" action="<?= e($formUrl) ?>" class="admin-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="stock">
                        <fieldset class="radio-row">
                            <legend class="label">Type of change</legend>
                            <label class="check"><input type="radio" name="mode" value="restock" checked> Restock (add items)</label>
                            <label class="check"><input type="radio" name="mode" value="adjust"> Correct stock (+ or −)</label>
                        </fieldset>
                        <div class="field-row">
                            <div class="field field--small">
                                <label for="quantity">Quantity</label>
                                <input type="number" id="quantity" name="quantity" step="1" required placeholder="e.g. 10 or -2">
                            </div>
                            <div class="field">
                                <label for="note">Note <span class="optional">(required for corrections)</span></label>
                                <input type="text" id="note" name="note" maxlength="255" placeholder="e.g. Delivery from supplier / 2 damaged">
                            </div>
                        </div>
                        <button type="submit" class="btn btn--primary btn--small">Update stock</button>
                    </form>

                    <h3 class="subhead">Recent stock movements</h3>
                    <?php if ($movements === []): ?>
                        <p class="muted">No stock movements yet.</p>
                    <?php else: ?>
                        <?= View::partial('movement-list', ['movements' => $movements, 'showProduct' => false]) ?>
                        <p><a href="<?= e(url('admin/inventory.php', ['product' => $product['id']])) ?>">Full stock history &rarr;</a></p>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
