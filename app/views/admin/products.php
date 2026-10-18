<?php
/** Admin product list. Variables: $filters, $result, $categories. */
$params = array_filter($filters, static fn($v) => $v !== '');
?>
<div class="toolbar">
    <form method="get" action="<?= e(url('admin/products.php')) ?>" class="filters">
        <label class="sr-only" for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Search name or SKU" maxlength="100">
        <label class="sr-only" for="category">Category</label>
        <select id="category" name="category">
            <option value="">All categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= e((string) $c['id']) ?>"<?= $filters['category'] === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="sr-only" for="status">Status</label>
        <select id="status" name="status">
            <option value="">Any status</option>
            <option value="active"<?= $filters['status'] === 'active' ? ' selected' : '' ?>>Active</option>
            <option value="inactive"<?= $filters['status'] === 'inactive' ? ' selected' : '' ?>>Inactive</option>
        </select>
        <label class="sr-only" for="stock">Stock</label>
        <select id="stock" name="stock">
            <option value="">Any stock</option>
            <option value="low"<?= $filters['stock'] === 'low' ? ' selected' : '' ?>>Low stock</option>
            <option value="out"<?= $filters['stock'] === 'out' ? ' selected' : '' ?>>Out of stock</option>
        </select>
        <button type="submit" class="btn btn--ghost">Filter</button>
        <?php if ($params !== []): ?><a href="<?= e(url('admin/products.php')) ?>">Clear</a><?php endif; ?>
    </form>
    <a class="btn btn--primary" href="<?= e(url('admin/product-edit.php')) ?>">+ Add product</a>
</div>

<section class="admin-panel">
    <header class="admin-panel__header">
        <h2 class="admin-panel__title"><?= e((string) $result['total']) ?> product<?= $result['total'] === 1 ? '' : 's' ?></h2>
    </header>
    <?php if ($result['items'] === []): ?>
        <p class="empty-state">No products match these filters.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th scope="col">Product</th><th scope="col">Category</th><th scope="col" class="num">Price</th><th scope="col" class="num">Stock</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($result['items'] as $p): ?>
                    <?php $stock = (int) $p['stock_quantity']; ?>
                    <tr>
                        <td>
                            <div class="product-cell">
                                <img src="<?= e(product_image_url($p['image'])) ?>" alt="" width="44" height="44">
                                <div>
                                    <a href="<?= e(url('admin/product-edit.php', ['id' => $p['id']])) ?>"><strong><?= e($p['name']) ?></strong></a>
                                    <br><small class="muted"><?= e($p['sku'] ?? 'No SKU') ?><?= (int) $p['is_featured'] === 1 ? ' · Featured' : '' ?><?= (int) ($p['is_customizable'] ?? 0) === 1 ? ' · Personalised' : '' ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?= e($p['category_name']) ?></td>
                        <td class="num"><?= e(money($p['price'])) ?><?php if (($sale = sale_info($p)) !== null): ?><br><small class="muted">sale −<?= e((string) $sale['percent']) ?>%, was <s><?= e(money($sale['was'])) ?></s></small><?php endif; ?></td>
                        <td class="num">
                            <?= e((string) $stock) ?>
                            <?php if ($stock === 0): ?><br><span class="badge badge--danger">Out</span>
                            <?php elseif ($stock <= (int) $p['low_stock_threshold']): ?><br><span class="badge badge--warning">Low</span><?php endif; ?>
                        </td>
                        <td><?= (int) $p['is_active'] === 1 ? '<span class="badge badge--success">Active</span>' : '<span class="badge badge--neutral">Inactive</span>' ?></td>
                        <td class="actions">
                            <a class="btn btn--ghost btn--small" href="<?= e(url('admin/product-edit.php', ['id' => $p['id']])) ?>">Edit</a>
                            <form method="post" action="<?= e(url('admin/products.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $p['id']) ?>">
                                <input type="hidden" name="action" value="<?= (int) $p['is_active'] === 1 ? 'deactivate' : 'activate' ?>">
                                <input type="hidden" name="return" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
                                <button type="submit" class="btn btn--ghost btn--small"><?= (int) $p['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                            </form>
                            <?php if ((int) $p['is_active'] === 1): ?>
                                <a class="btn btn--ghost btn--small" href="<?= e(url('product.php', ['slug' => $p['slug']])) ?>" target="_blank" rel="noopener">View</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= View::partial('pagination', ['page' => $result['page'], 'pages' => $result['pages'], 'path' => 'admin/products.php', 'params' => $params]) ?>
    <?php endif; ?>
</section>
