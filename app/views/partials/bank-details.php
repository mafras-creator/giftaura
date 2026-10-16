<?php
/**
 * Bank transfer instructions. Variables: $bank (PaymentService::bankDetails()), $order.
 * If the store has not entered its bank details yet, say so instead of inventing them.
 */
$configured = $bank['bank_name'] !== '' && $bank['bank_account_number'] !== '';
?>
<div class="bank-box">
    <h3 class="bank-box__title">How to pay by bank transfer</h3>
    <?php if ($configured): ?>
        <p>Please transfer <strong><?= e(money($order['total_amount'])) ?></strong> to:</p>
        <dl class="bank-box__details">
            <div><dt>Bank</dt><dd><?= e($bank['bank_name']) ?></dd></div>
            <?php if ($bank['bank_branch'] !== ''): ?><div><dt>Branch</dt><dd><?= e($bank['bank_branch']) ?></dd></div><?php endif; ?>
            <?php if ($bank['bank_account_name'] !== ''): ?><div><dt>Account name</dt><dd><?= e($bank['bank_account_name']) ?></dd></div><?php endif; ?>
            <div><dt>Account number</dt><dd><?= e($bank['bank_account_number']) ?></dd></div>
            <div><dt>Reference</dt><dd><?= e($order['order_number']) ?></dd></div>
        </dl>
        <p class="muted">Use your order number as the payment reference. After transferring, enter the reference from your bank slip on your order page.</p>
    <?php else: ?>
        <p>The store has not published its bank account details on the website yet. Please <a href="<?= e(url('contact.php')) ?>">contact the store</a> for the account details, and quote your order number <strong><?= e($order['order_number']) ?></strong>.</p>
    <?php endif; ?>
</div>
