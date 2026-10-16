<?php
/** Admin categories. Variables: $categories, $parents, $editing, $errors, $old. */
?>
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
                    <thead><tr><th scope="col">Name</th><th scope="col">Web address</th><th scope="col" class="num">Products</th><th scope="col" class="num">Order</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td><?= $c['parent_id'] !== null ? '<span class="muted" aria-hidden="true">↳ </span>' : '' ?><strong><?= e($c['name']) ?></strong>
                                <?php if ((int) $c['child_count'] > 0): ?><br><small class="muted"><?= e((string) $c['child_count']) ?> subcategor<?= (int) $c['child_count'] === 1 ? 'y' : 'ies' ?></small><?php endif; ?></td>
                            <td><code><?= e($c['slug']) ?></code></td>
                            <td class="num"><?= e((string) $c['product_count']) ?></td>
                            <td class="num"><?= e((string) $c['sort_order']) ?></td>
                            <td><?= (int) $c['is_active'] === 1 ? '<span class="badge badge--success">Active</span>' : '<span class="badge badge--neutral">Inactive</span>' ?></td>
                            <td class="actions">
                                <a class="btn btn--ghost btn--small" href="<?= e(url('admin/categories.php', ['edit' => $c['id']])) ?>">Edit</a>
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
            <h2 class="admin-panel__title"><?= $editing ? 'Edit category' : 'Add a category' ?></h2>
            <?php if ($editing): ?><a class="btn btn--ghost btn--small" href="<?= e(url('admin/categories.php')) ?>">Cancel</a><?php endif; ?>
        </header>
        <form method="post" action="<?= e(url('admin/categories.php')) ?>" class="admin-form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
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
                <label for="parent_id">Parent category</label>
                <select id="parent_id" name="parent_id"<?= field_attrs($errors, 'parent_id') ?>>
                    <option value="">— None (top-level category) —</option>
                    <?php foreach ($parents as $p): ?>
                        <option value="<?= e((string) $p['id']) ?>"<?= (string) ($old['parent_id'] ?? '') === (string) $p['id'] ? ' selected' : '' ?>><?= e($p['name']) ?><?= (int) $p['is_active'] === 1 ? '' : ' (inactive)' ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'parent_id') ?>
            </div>
            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3" maxlength="500"<?= field_attrs($errors, 'description') ?>><?= e($old['description'] ?? '') ?></textarea>
                <?= field_error($errors, 'description') ?>
            </div>
            <div class="field field--small">
                <label for="sort_order">Display order</label>
                <input type="number" id="sort_order" name="sort_order" value="<?= e((string) ($old['sort_order'] ?? '0')) ?>" step="1"<?= field_attrs($errors, 'sort_order') ?>>
                <?= field_error($errors, 'sort_order') ?>
                <p class="hint">Smaller numbers are shown first.</p>
            </div>
            <label class="check"><input type="checkbox" name="is_active" value="1"<?= (string) ($old['is_active'] ?? '1') === '1' ? ' checked' : '' ?>> Active (visible in the shop)</label>
            <button type="submit" class="btn btn--primary"><?= $editing ? 'Save changes' : 'Add category' ?></button>
        </form>
    </section>
</div>
