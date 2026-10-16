<?php
/**
 * Order progress tracker + timeline.
 * Variables: $order (row), $history (rows from OrderService::history()).
 */
$status = $order['status'];
$steps = OrderService::PROGRESS;
$currentIndex = array_search($status, $steps, true);
$reachedAt = [];
foreach ($history as $h) {
    $reachedAt[$h['to_status']] = $h['created_at'];
}
?>
<?php if ($status === 'cancelled'): ?>
    <div class="alert alert--error"><span>This order was cancelled on <?= e(format_datetime($reachedAt['cancelled'] ?? null)) ?>.</span></div>
<?php else: ?>
    <ol class="progress" aria-label="Order progress">
        <?php foreach ($steps as $i => $step): ?>
            <?php $state = $i < $currentIndex ? 'done' : ($i === $currentIndex ? 'current' : 'todo'); ?>
            <li class="progress__step progress__step--<?= e($state) ?>"<?= $state === 'current' ? ' aria-current="step"' : '' ?>>
                <span class="progress__dot" aria-hidden="true"></span>
                <span class="progress__label"><?= e(OrderService::STATUSES[$step]) ?></span>
                <?php if (isset($reachedAt[$step])): ?>
                    <span class="progress__date"><?= e(format_datetime($reachedAt[$step])) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

<?php if ($history !== []): ?>
    <details class="timeline">
        <summary>Full history (<?= e((string) count($history)) ?>)</summary>
        <ul>
            <?php foreach ($history as $h): ?>
                <li>
                    <strong><?= e(OrderService::STATUSES[$h['to_status']] ?? $h['to_status']) ?></strong>
                    <span class="muted"><?= e(format_datetime($h['created_at'])) ?></span>
                    <?php if (!empty($h['note'])): ?><br><span><?= e($h['note']) ?></span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </details>
<?php endif; ?>
