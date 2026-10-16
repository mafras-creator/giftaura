<?php
/** Admin customer detail. Variables: $customer, $orders, $addresses, $tempPassword. */
$active = (int) $customer['is_active'] === 1;
?>
<p><a href="<?= e(url('admin/customers.php')) ?>">&larr; All customers</a></p>

<?php if ($tempPassword !== null): ?>
    <div class="alert alert--success temp-password" role="status">
        <span>
            New temporary password for <?= e($customer['full_name']) ?>: <code class="temp-password__value"><?= e($tempPassword) ?></code><br>
            Give it to the customer privately (e.g. by phone) and ask them to change it under <em>My account → Change password</em>.
            It is shown only this once and is not stored anywhere in readable form.
        </span>
    </div>
<?php endif; ?>

<div class="admin-columns admin-columns--wide-right">
    <div class="admin-stack">
        <section class="admin-panel">
            <header class="admin-panel__header">
                <h2 class="admin-panel__title"><?= e($customer['full_name']) ?></h2>
                <?= $active ? '<span class="badge badge--success">Active</span>' : '<span class="badge badge--neutral">Inactive</span>' ?>
            </header>
            <dl class="kv">
                <div><dt>Email</dt><dd><?= e($customer['email']) ?></dd></div>
                <div><dt>Phone</dt><dd><?= e($customer['phone'] ?? '—') ?></dd></div>
                <div><dt>Joined</dt><dd><?= e(format_datetime($customer['created_at'])) ?></dd></div>
                <div><dt>Last login</dt><dd><?= e(format_datetime($customer['last_login_at']) ?: 'Never') ?></dd></div>
                <div><dt>Orders</dt><dd><?= e((string) $customer['order_count']) ?></dd></div>
                <div><dt>Total spent</dt><dd><?= e(money($customer['total_spent'])) ?></dd></div>
            </dl>
        </section>

        <section class="admin-panel">
            <header class="admin-panel__header"><h2 class="admin-panel__title">Account actions</h2></header>
            <div class="panel-body admin-form">
                <form method="post" action="<?= e(url('admin/customer.php', ['id' => $customer['id']])) ?>"
                      data-confirm="<?= $active ? 'Deactivate this account? The customer will not be able to log in.' : 'Activate this account?' ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $active ? 'deactivate' : 'activate' ?>">
                    <button type="submit" class="btn <?= $active ? 'btn--danger-ghost' : 'btn--primary' ?>"><?= $active ? 'Deactivate account' : 'Activate account' ?></button>
                </form>
                <form method="post" action="<?= e(url('admin/customer.php', ['id' => $customer['id']])) ?>"
                      data-confirm="Create a new temporary password? The customer's current password will stop working.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="temp_password">
                    <button type="submit" class="btn btn--ghost">Set temporary password</button>
                </form>
                <p class="hint">Use this when a customer forgets their password.</p>
            </div>
        </section>

        <section class="admin-panel">
            <header class="admin-panel__header"><h2 class="admin-panel__title">Saved addresses</h2></header>
            <?php if ($addresses === []): ?>
                <p class="empty-state">No saved addresses.</p>
            <?php else: ?>
                <ul class="plain-list">
                    <?php foreach ($addresses as $a): ?>
                        <li><strong><?= e($a['label']) ?></strong><?= (int) $a['is_default'] === 1 ? ' (default)' : '' ?><br>
                            <?= e($a['recipient_name']) ?>, <?= e($a['phone']) ?><br>
                            <?= e($a['address_line1']) ?><?= $a['address_line2'] ? ', ' . e($a['address_line2']) : '' ?>, <?= e($a['city']) ?>, <?= e($a['district']) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <section class="admin-panel">
        <header class="admin-panel__header"><h2 class="admin-panel__title">Orders</h2></header>
        <?php if ($orders === []): ?>
            <p class="empty-state">This customer has not placed any orders.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th scope="col">Order</th><th scope="col">Date</th><th scope="col">Status</th><th scope="col">Payment</th><th scope="col" class="num">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><a href="<?= e(url('admin/order.php', ['id' => $o['id']])) ?>"><?= e($o['order_number']) ?></a></td>
                            <td><?= e(format_datetime($o['created_at'])) ?></td>
                            <td><?= order_status_badge($o['status']) ?></td>
                            <td><?= payment_status_badge($o['payment_status']) ?></td>
                            <td class="num"><?= e(money($o['total_amount'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
