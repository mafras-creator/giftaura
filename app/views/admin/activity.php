<?php
/** Activity log. Variables: $types, $type, $result. */
?>
<form method="get" action="<?= e(url('admin/activity.php')) ?>" class="filters filters--panel">
    <label for="type">Show</label>
    <select id="type" name="type" data-auto-submit>
        <option value="">All activity</option>
        <?php foreach ($types as $t): ?>
            <option value="<?= e($t) ?>"<?= $t === $type ? ' selected' : '' ?>><?= e(ucfirst($t)) ?></option>
        <?php endforeach; ?>
    </select>
    <noscript><button type="submit" class="btn btn--ghost btn--small">Show</button></noscript>
</form>

<section class="admin-panel">
    <header class="admin-panel__header"><h2 class="admin-panel__title"><?= e((string) $result['total']) ?> entries</h2></header>
    <?php if ($result['items'] === []): ?>
        <p class="empty-state">No activity recorded yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table data-table--compact">
                <thead><tr><th scope="col">When</th><th scope="col">Administrator</th><th scope="col">Action</th><th scope="col">Item</th><th scope="col">Details</th></tr></thead>
                <tbody>
                <?php foreach ($result['items'] as $l): ?>
                    <?php
                    $link = match ($l['entity_type']) {
                        'order'    => $l['entity_id'] ? url('admin/order.php', ['id' => $l['entity_id']]) : null,
                        'product'  => $l['entity_id'] ? url('admin/product-edit.php', ['id' => $l['entity_id']]) : null,
                        'customer' => $l['entity_id'] ? url('admin/customer.php', ['id' => $l['entity_id']]) : null,
                        'category' => $l['entity_id'] ? url('admin/categories.php', ['edit' => $l['entity_id']]) : null,
                        'banner'   => $l['entity_id'] ? url('admin/banners.php', ['edit' => $l['entity_id']]) : null,
                        default    => null,
                    };
                    ?>
                    <tr>
                        <td><?= e(format_datetime($l['created_at'])) ?></td>
                        <td><?= e($l['admin_name'] ?? '—') ?><br><small class="muted"><?= e($l['ip_address'] ?? '') ?></small></td>
                        <td><code><?= e($l['action']) ?></code></td>
                        <td><?php if ($link): ?><a href="<?= e($link) ?>"><?= e($l['entity_type']) ?> #<?= e((string) $l['entity_id']) ?></a><?php else: ?><?= e($l['entity_type']) ?><?php endif; ?></td>
                        <td><?= e($l['details'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= View::partial('pagination', ['page' => $result['page'], 'pages' => $result['pages'], 'path' => 'admin/activity.php', 'params' => $type !== '' ? ['type' => $type] : []]) ?>
    <?php endif; ?>
</section>
