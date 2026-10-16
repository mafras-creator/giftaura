<?php
/** Inventory. Variables: $lowStock, $products, $productFilter, $movements. */
$selectedName = '';
foreach ($products as $p) {
    if ((int) $p['id'] === $productFilter) {
        $selectedName = $p['name'];
    }
}
?>
<div class="admin-columns">
    <section class="admin-panel">
        <header class="admin-panel__header">
            <h2 class="admin-panel__title">Low-stock and out-of-stock products</h2>
            <a class="btn btn--ghost btn--small" href="<?= e(url('admin/report-export.php', ['report' => 'low_stock'])) ?>">Export CSV</a>
        </header>
        <?php if ($lowStock === []): ?>
            <p class="empty-state">All active products are above their alert level.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th scope="col">Product</th><th scope="col" class="num">In stock</th><th scope="col" class="num">Alert at</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($lowStock as $r): ?>
                        <tr>
                            <td><a href="<?= e(url('admin/product-edit.php', ['id' => $r['id']])) ?>"><?= e($r['name']) ?></a><br><small class="muted"><?= e($r['sku'] ?? '') ?></small></td>
                            <td class="num"><?= e((string) $r['stock_quantity']) ?></td>
                            <td class="num"><?= e((string) $r['low_stock_threshold']) ?></td>
                            <td><?= $r['state'] === 'Out of stock' ? '<span class="badge badge--danger">Out of stock</span>' : '<span class="badge badge--warning">Low stock</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-panel">
        <header class="admin-panel__header"><h2 class="admin-panel__title">Quick stock update</h2></header>
        <form method="post" action="<?= e(url('admin/inventory.php')) ?>" class="admin-form">
            <?= csrf_field() ?>
            <div class="field">
                <label for="product_id">Product</label>
                <select id="product_id" name="product_id" required>
                    <option value="">Choose…</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= e((string) $p['id']) ?>"<?= (int) $p['id'] === $productFilter ? ' selected' : '' ?>><?= e($p['name']) ?> (<?= e((string) $p['stock_quantity']) ?> in stock)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <fieldset class="radio-row">
                <legend class="label">Type of change</legend>
                <label class="check"><input type="radio" name="mode" value="restock" checked> Restock</label>
                <label class="check"><input type="radio" name="mode" value="adjust"> Correction (+ or −)</label>
            </fieldset>
            <div class="field-row">
                <div class="field field--small">
                    <label for="quantity">Quantity</label>
                    <input type="number" id="quantity" name="quantity" step="1" required>
                </div>
                <div class="field">
                    <label for="note">Note</label>
                    <input type="text" id="note" name="note" maxlength="255" placeholder="Required for corrections">
                </div>
            </div>
            <button type="submit" class="btn btn--primary btn--small">Update stock</button>
        </form>
    </section>
</div>

<section class="admin-panel">
    <header class="admin-panel__header">
        <h2 class="admin-panel__title">Stock history<?= $selectedName !== '' ? ': ' . e($selectedName) : '' ?></h2>
        <form method="get" action="<?= e(url('admin/inventory.php')) ?>" class="filters">
            <label class="sr-only" for="product">Product</label>
            <select id="product" name="product" data-auto-submit>
                <option value="">All products</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= e((string) $p['id']) ?>"<?= (int) $p['id'] === $productFilter ? ' selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn--ghost btn--small">Show</button></noscript>
        </form>
    </header>
    <?php if ($movements['items'] === []): ?>
        <p class="empty-state">No stock movements recorded.</p>
    <?php else: ?>
        <?= View::partial('movement-list', ['movements' => $movements['items'], 'showProduct' => true]) ?>
        <?= View::partial('pagination', ['page' => $movements['page'], 'pages' => $movements['pages'], 'path' => 'admin/inventory.php',
                                         'params' => $productFilter ? ['product' => $productFilter] : []]) ?>
    <?php endif; ?>
</section>
