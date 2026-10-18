<?php
/** Admin customers list. Variables: $filters, $result. */
?>
<form method="get" action="<?= e(url('admin/customers.php')) ?>" class="filters filters--panel">
    <label class="sr-only" for="q">Search</label>
    <input type="search" id="q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Name, email or phone" maxlength="100">
    <label class="sr-only" for="status">Status</label>
    <select id="status" name="status">
        <option value="">All accounts</option>
        <option value="active"<?= ($filters['status'] ?? '') === 'active' ? ' selected' : '' ?>>Active</option>
        <option value="inactive"<?= ($filters['status'] ?? '') === 'inactive' ? ' selected' : '' ?>>Inactive</option>
    </select>
    <button type="submit" class="btn btn--ghost">Filter</button>
    <?php if ($filters !== []): ?><a href="<?= e(url('admin/customers.php')) ?>">Clear</a><?php endif; ?>
</form>

<section class="admin-panel">
    <header class="admin-panel__header"><h2 class="admin-panel__title"><?= e((string) $result['total']) ?> customer<?= $result['total'] === 1 ? '' : 's' ?></h2></header>
    <?php if ($result['items'] === []): ?>
        <p class="empty-state">No customers found.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th scope="col">Customer</th><th scope="col">Phone</th><th scope="col">Joined</th><th scope="col" class="num">Orders</th><th scope="col" class="num">Total spent</th><th scope="col">Status</th></tr></thead>
                <tbody>
                <?php foreach ($result['items'] as $c): ?>
                    <tr>
                        <td><a href="<?= e(url('admin/customer.php', ['id' => $c['id']])) ?>"><strong><?= e($c['full_name']) ?></strong></a><br><small class="muted"><?= e($c['email']) ?></small><?php if ($c['email_verified_at'] === null): ?> <span class="badge badge--warning">E-mail not verified</span><?php endif; ?></td>
                        <td><?= e($c['phone'] ?? '') ?></td>
                        <td><?= e(format_datetime($c['created_at'])) ?></td>
                        <td class="num"><?= e((string) $c['order_count']) ?></td>
                        <td class="num"><?= e(money($c['total_spent'])) ?></td>
                        <td><?= (int) $c['is_active'] === 1 ? '<span class="badge badge--success">Active</span>' : '<span class="badge badge--neutral">Inactive</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= View::partial('pagination', ['page' => $result['page'], 'pages' => $result['pages'], 'path' => 'admin/customers.php', 'params' => $filters]) ?>
    <?php endif; ?>
</section>
