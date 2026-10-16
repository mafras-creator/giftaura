<?php
/** My orders. Variables: $user, $orders (items, total, page, pages). */
?>
<section class="section">
    <div class="container account-layout">
        <div>
            <h1 class="page-title">My account</h1>
            <p class="page-lead">Hello, <?= e($user['full_name']) ?>.</p>
            <?= View::partial('account-nav') ?>
        </div>

        <div class="panel">
            <h2 class="panel__title">My orders</h2>
            <?php if ($orders['items'] === []): ?>
                <div class="empty-state">
                    <p>You have not placed any orders yet.</p>
                    <p><a class="btn btn--primary" href="<?= e(url('products.php')) ?>">Browse products</a></p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th scope="col">Order</th><th scope="col">Date</th><th scope="col">Items</th>
                                <th scope="col">Status</th><th scope="col">Payment</th><th scope="col" class="num">Total</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders['items'] as $o): ?>
                                <tr>
                                    <td><a href="<?= e(url('account/order.php', ['order' => $o['order_number']])) ?>"><?= e($o['order_number']) ?></a></td>
                                    <td><?= e(format_datetime($o['created_at'])) ?></td>
                                    <td><?= e((string) (int) $o['units']) ?></td>
                                    <td><?= order_status_badge($o['status']) ?></td>
                                    <td><?= e(PaymentService::METHODS[$o['payment_method']] ?? '') ?><br><?= payment_status_badge($o['payment_status']) ?></td>
                                    <td class="num"><?= e(money($o['total_amount'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?= View::partial('pagination', ['page' => $orders['page'], 'pages' => $orders['pages'], 'path' => 'account/orders.php', 'params' => []]) ?>
            <?php endif; ?>
        </div>
    </div>
</section>
