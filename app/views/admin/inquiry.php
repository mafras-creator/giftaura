<?php
/** Admin - one inquiry. Variables: $inquiry, $replies, $customer, $order, $error, $draft. */
$ref = InquiryService::reference((int) $inquiry['id']);
?>
<p><a href="<?= e(url('admin/inquiries.php')) ?>">← All inquiries</a></p>

<div class="admin-columns admin-columns--wide-left">
    <section class="admin-panel">
        <header class="admin-panel__header">
            <h2 class="admin-panel__title"><?= e(InquiryService::SUBJECTS[$inquiry['subject']] ?? $inquiry['subject']) ?></h2>
            <span class="badge badge--<?= $inquiry['status'] === 'new' ? 'warning' : ($inquiry['status'] === 'resolved' ? 'success' : 'neutral') ?>"><?= e(InquiryService::STATUSES[$inquiry['status']]) ?></span>
        </header>
        <div class="inquiry-thread">
            <div class="inquiry-msg">
                <p class="inquiry-msg__meta"><strong><?= e($inquiry['name']) ?></strong> · <?= e(format_datetime($inquiry['created_at'])) ?></p>
                <p class="inquiry-msg__text"><?= e($inquiry['message']) ?></p>
            </div>
            <?php foreach ($replies as $r): ?>
                <div class="inquiry-msg inquiry-msg--reply">
                    <p class="inquiry-msg__meta"><strong><?= e($r['admin_name'] ?? 'Former staff member') ?></strong><?= $r['admin_role'] === 'staff' ? ' (staff)' : '' ?> replied · <?= e(format_datetime($r['created_at'])) ?></p>
                    <p class="inquiry-msg__text"><?= e($r['message']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <h3 class="inquiry-reply-title">Reply to <?= e($inquiry['email']) ?></h3>
        <?php if ($error !== null): ?><div class="alert alert--error panel-alert" role="alert"><span><?= e($error) ?></span></div><?php endif; ?>
        <form method="post" action="<?= e(url('admin/inquiry.php', ['id' => $inquiry['id']])) ?>" class="admin-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reply">
            <div class="field">
                <label for="message">Your reply</label>
                <textarea id="message" name="message" rows="7" maxlength="<?= e((string) InquiryService::REPLY_MAX) ?>" required><?= e($draft) ?></textarea>
                <p class="hint">Sent by e-mail from the store address with the reference <?= e($ref) ?>. Do not ask for card numbers or passwords.</p>
            </div>
            <div class="field">
                <label for="reply-status">After sending, mark as</label>
                <select id="reply-status" name="status">
                    <option value="in_progress">In progress (waiting for the customer or for us)</option>
                    <option value="resolved">Resolved</option>
                </select>
            </div>
            <button type="submit" class="btn btn--primary" data-loading-text="Sending…">Send reply</button>
        </form>
    </section>

    <aside class="admin-panel">
        <header class="admin-panel__header"><h2 class="admin-panel__title"><?= e($ref) ?></h2></header>
        <dl class="kv">
            <div><dt>Name</dt><dd><?= e($inquiry['name']) ?></dd></div>
            <div><dt>E-mail</dt><dd><a href="mailto:<?= e($inquiry['email']) ?>"><?= e($inquiry['email']) ?></a></dd></div>
            <?php if ($inquiry['phone'] !== null): ?><div><dt>Mobile</dt><dd><?= e($inquiry['phone']) ?></dd></div><?php endif; ?>
            <div><dt>Account</dt><dd><?php if ($customer !== null): ?><?= Auth::can('customers') ? '<a href="' . e(url('admin/customer.php', ['id' => $customer['id']])) . '">' . e($customer['full_name']) . '</a>' : e($customer['full_name']) ?><?php else: ?>Not logged in (guest)<?php endif; ?></dd></div>
            <?php if ($inquiry['order_number'] !== null): ?>
                <div><dt>Order</dt><dd><?php if ($order !== null): ?><a href="<?= e(url('admin/order.php', ['id' => $order['id']])) ?>"><?= e($order['order_number']) ?></a> (<?= e(str_replace('_', ' ', $order['status'])) ?>)<?php else: ?><?= e($inquiry['order_number']) ?><?= Auth::can('orders') ? ' <small class="muted">(no such order)</small>' : '' ?><?php endif; ?></dd></div>
            <?php endif; ?>
            <div><dt>Received</dt><dd><?= e(format_datetime($inquiry['created_at'])) ?></dd></div>
            <?php if ($inquiry['last_reply_at'] !== null): ?><div><dt>Last reply</dt><dd><?= e(format_datetime($inquiry['last_reply_at'])) ?></dd></div><?php endif; ?>
        </dl>
        <form method="post" action="<?= e(url('admin/inquiry.php', ['id' => $inquiry['id']])) ?>" class="admin-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="status">
            <div class="field">
            <label for="status">Change status without replying</label>
            <select id="status" name="status">
                <?php foreach (InquiryService::STATUSES as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= $inquiry['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            </div>
            <button type="submit" class="btn btn--ghost btn--small">Save status</button>
        </form>
    </aside>
</div>
