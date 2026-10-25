<?php
/** My account - inquiries. Variables: $user, $items (InquiryService::forUser). */
?>
<section class="section">
    <div class="container account-layout">
        <div>
            <?= View::partial('account-nav') ?>
        </div>

        <div class="account-stack">
            <div class="panel">
                <h2 class="panel__title">My inquiries <span class="muted">(<?= e((string) count($items)) ?>)</span></h2>
                <p class="muted">Messages you sent from the Contact page while logged in, with our replies. Replies are also sent to your e-mail.</p>
                <p><a class="btn btn--primary" href="<?= e(url('contact.php')) ?>#message">Send a new message</a></p>
                <?php if ($items === []): ?>
                    <div class="empty-state"><p><strong>You have not sent us a message yet.</strong></p></div>
                <?php else: ?>
                    <ul class="inquiry-list">
                        <?php foreach ($items as $item): ?>
                            <li class="inquiry" id="inq-<?= e((string) $item['id']) ?>">
                                <div class="inquiry__head">
                                    <strong><?= e(InquiryService::reference((int) $item['id'])) ?> · <?= e(InquiryService::SUBJECTS[$item['subject']] ?? $item['subject']) ?></strong>
                                    <span class="badge inquiry-status inquiry-status--<?= e($item['status']) ?>"><?= e($item['status'] === 'new' ? 'Sent' : InquiryService::STATUSES[$item['status']]) ?></span>
                                </div>
                                <p class="muted inquiry__meta">Sent <?= e(format_datetime($item['created_at'])) ?><?= $item['order_number'] !== null ? ' · Order ' . e($item['order_number']) : '' ?></p>
                                <p class="inquiry__text"><?= e($item['message']) ?></p>
                                <?php foreach ($item['replies'] as $reply): ?>
                                    <div class="inquiry__reply">
                                        <p class="inquiry__meta"><strong>GiftAura</strong> replied <?= e(format_datetime($reply['created_at'])) ?></p>
                                        <p class="inquiry__text"><?= e($reply['message']) ?></p>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($item['replies'] === []): ?><p class="muted inquiry__meta">We have not replied yet. We usually answer within one working day.</p><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
