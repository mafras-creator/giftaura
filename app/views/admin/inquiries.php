<?php
/** Admin - customer inquiries list. Variables: $filters, $result. */
?>
<p class="admin-lead">Messages sent from the Contact page. Open a message to reply: the reply is e-mailed to the customer and,
    if they were logged in, also shown under My account → My inquiries.</p>

<form method="get" action="<?= e(url('admin/inquiries.php')) ?>" class="filters filters--panel">
    <label class="sr-only" for="q">Search</label>
    <input type="search" id="q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Name, e-mail, order or text" maxlength="100">
    <label class="sr-only" for="subject">Subject</label>
    <select id="subject" name="subject">
        <option value="">All subjects</option>
        <?php foreach (InquiryService::SUBJECTS as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= ($filters['subject'] ?? '') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <label class="sr-only" for="status">Status</label>
    <select id="status" name="status">
        <option value="">All statuses</option>
        <?php foreach (InquiryService::STATUSES as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= ($filters['status'] ?? '') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn--ghost">Filter</button>
    <?php if ($filters !== []): ?><a href="<?= e(url('admin/inquiries.php')) ?>">Clear</a><?php endif; ?>
</form>

<section class="admin-panel">
    <header class="admin-panel__header"><h2 class="admin-panel__title"><?= e((string) $result['total']) ?> inquir<?= $result['total'] === 1 ? 'y' : 'ies' ?></h2></header>
    <?php if ($result['items'] === []): ?>
        <p class="empty-state">No inquiries<?= $filters !== [] ? ' match these filters' : ' yet' ?>.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table data-table--compact">
                <thead><tr><th scope="col">Reference</th><th scope="col">Received</th><th scope="col">From</th><th scope="col">Subject and message</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Open</span></th></tr></thead>
                <tbody>
                <?php foreach ($result['items'] as $i): ?>
                    <tr>
                        <td><a href="<?= e(url('admin/inquiry.php', ['id' => $i['id']])) ?>"><strong><?= e(InquiryService::reference((int) $i['id'])) ?></strong></a></td>
                        <td><?= e(format_datetime($i['created_at'])) ?></td>
                        <td><?= e($i['name']) ?><br><small class="muted"><?= e($i['email']) ?><?= $i['user_id'] !== null ? ' · customer account' : '' ?></small></td>
                        <td class="review-cell"><strong><?= e(InquiryService::SUBJECTS[$i['subject']] ?? $i['subject']) ?></strong><?= $i['order_number'] !== null ? ' <small class="muted">· ' . e($i['order_number']) . '</small>' : '' ?>
                            <p class="review-cell__text"><?= e(mb_strimwidth($i['message'], 0, 140, '…')) ?></p></td>
                        <td><span class="badge badge--<?= $i['status'] === 'new' ? 'warning' : ($i['status'] === 'resolved' ? 'success' : 'neutral') ?>"><?= e(InquiryService::STATUSES[$i['status']]) ?></span>
                            <?php if ((int) $i['reply_count'] > 0): ?><br><small class="muted"><?= e((string) $i['reply_count']) ?> repl<?= (int) $i['reply_count'] === 1 ? 'y' : 'ies' ?></small><?php endif; ?></td>
                        <td><a class="btn btn--ghost btn--small" href="<?= e(url('admin/inquiry.php', ['id' => $i['id']])) ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= View::partial('pagination', ['page' => $result['page'], 'pages' => $result['pages'], 'path' => 'admin/inquiries.php', 'params' => $filters]) ?>
    <?php endif; ?>
</section>
