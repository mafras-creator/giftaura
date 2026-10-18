<?php
/** E-mail log. Variables: $filters, $transport, $result. */
$statusBadges = ['sent' => 'success', 'saved' => 'neutral', 'skipped' => 'warning', 'failed' => 'danger'];
$modes = [
    'smtp' => 'E-mails are sent through ' . (string) config('mail.host') . ' from ' . Mailer::fromEmail() . '.',
    'file' => 'TEST MODE: e-mails are not sent; they are saved as files in the storage\\mail folder on the server.',
    'off'  => 'E-mail is switched off: nothing is sent, attempts are only listed here.',
];
?>
<div class="alert alert--<?= $transport === 'smtp' ? 'info' : 'warning' ?>">
    <span><?= e($modes[$transport] ?? $transport) ?> Change this with <code>scripts\setup_mail.php</code> on the server.</span>
</div>

<form method="get" action="<?= e(url('admin/emails.php')) ?>" class="filters filters--panel">
    <label class="sr-only" for="q">Recipient</label>
    <input type="search" id="q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Recipient e-mail" maxlength="100">
    <label class="sr-only" for="template">Type</label>
    <select id="template" name="template">
        <option value="">All types</option>
        <?php foreach (EmailService::TEMPLATES as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= ($filters['template'] ?? '') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <label class="sr-only" for="status">Result</label>
    <select id="status" name="status">
        <option value="">All results</option>
        <?php foreach (array_keys($statusBadges) as $s): ?>
            <option value="<?= e($s) ?>"<?= ($filters['status'] ?? '') === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn--ghost">Filter</button>
    <?php if ($filters !== []): ?><a href="<?= e(url('admin/emails.php')) ?>">Clear</a><?php endif; ?>
</form>

<section class="admin-panel">
    <header class="admin-panel__header"><h2 class="admin-panel__title"><?= e((string) $result['total']) ?> e-mail<?= $result['total'] === 1 ? '' : 's' ?></h2></header>
    <?php if ($result['items'] === []): ?>
        <p class="empty-state">No e-mails recorded yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table data-table--compact">
                <thead><tr><th scope="col">When</th><th scope="col">To</th><th scope="col">Type</th><th scope="col">Subject</th><th scope="col">Order</th><th scope="col">Result</th></tr></thead>
                <tbody>
                <?php foreach ($result['items'] as $m): ?>
                    <tr>
                        <td><?= e(format_datetime($m['created_at'])) ?></td>
                        <td><?php if ($m['user_id'] !== null): ?><a href="<?= e(url('admin/customer.php', ['id' => $m['user_id']])) ?>"><?= e($m['recipient']) ?></a><?php else: ?><?= e($m['recipient']) ?><?php endif; ?></td>
                        <td><?= e(EmailService::TEMPLATES[$m['template']] ?? $m['template']) ?></td>
                        <td><?= e($m['subject']) ?></td>
                        <td><?php if ($m['order_number'] !== null): ?><a href="<?= e(url('admin/order.php', ['id' => $m['order_id']])) ?>"><?= e($m['order_number']) ?></a><?php else: ?>—<?php endif; ?></td>
                        <td>
                            <span class="badge badge--<?= e($statusBadges[$m['status']] ?? 'neutral') ?>"><?= e(ucfirst($m['status'])) ?></span>
                            <small class="muted"><?= e($m['transport']) ?></small>
                            <?php if ($m['error'] !== null): ?><br><small><?= e($m['error']) ?></small><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= View::partial('pagination', ['page' => $result['page'], 'pages' => $result['pages'], 'path' => 'admin/emails.php', 'params' => $filters]) ?>
    <?php endif; ?>
</section>
