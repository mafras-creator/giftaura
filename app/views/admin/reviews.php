<?php
/** Admin - product reviews. Variables: $filters, $result. */
$here = url('admin/reviews.php', $filters + ($result['page'] > 1 ? ['page' => $result['page']] : []));
?>
<p class="admin-lead">Customers can review a product after it has been delivered to them. Reviews appear on the product page at once.
    Hide a review that is rude, off-topic or contains private details; the customer still sees it in their account, marked "Hidden by the store".</p>

<form method="get" action="<?= e(url('admin/reviews.php')) ?>" class="filters filters--panel">
    <label class="sr-only" for="q">Product or customer</label>
    <input type="search" id="q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Product, customer or e-mail" maxlength="100">
    <label class="sr-only" for="rating">Stars</label>
    <select id="rating" name="rating">
        <option value="">All ratings</option>
        <?php for ($i = 5; $i >= 1; $i--): ?>
            <option value="<?= $i ?>"<?= ($filters['rating'] ?? '') === (string) $i ? ' selected' : '' ?>><?= $i ?> star<?= $i > 1 ? 's' : '' ?></option>
        <?php endfor; ?>
    </select>
    <label class="sr-only" for="status">Status</label>
    <select id="status" name="status">
        <option value="">Shown and hidden</option>
        <option value="visible"<?= ($filters['status'] ?? '') === 'visible' ? ' selected' : '' ?>>Shown</option>
        <option value="hidden"<?= ($filters['status'] ?? '') === 'hidden' ? ' selected' : '' ?>>Hidden</option>
    </select>
    <button type="submit" class="btn btn--ghost">Filter</button>
    <?php if ($filters !== []): ?><a href="<?= e(url('admin/reviews.php')) ?>">Clear</a><?php endif; ?>
</form>

<section class="admin-panel">
    <header class="admin-panel__header"><h2 class="admin-panel__title"><?= e((string) $result['total']) ?> review<?= $result['total'] === 1 ? '' : 's' ?></h2></header>
    <?php if ($result['items'] === []): ?>
        <p class="empty-state">No reviews<?= $filters !== [] ? ' match these filters' : ' yet' ?>.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table data-table--compact">
                <thead><tr><th scope="col">Date</th><th scope="col">Product</th><th scope="col">Customer</th><th scope="col">Rating and review</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($result['items'] as $r): ?>
                    <tr>
                        <td><?= e(format_datetime($r['created_at'])) ?></td>
                        <td><a href="<?= e(url('admin/product-edit.php', ['id' => $r['product_id']])) ?>"><?= e($r['product_name']) ?></a>
                            <?php if ($r['order_number'] !== null): ?><br><small class="muted">Order <a href="<?= e(url('admin/order.php', ['id' => $r['order_id']])) ?>"><?= e($r['order_number']) ?></a></small><?php endif; ?></td>
                        <td><a href="<?= e(url('admin/customer.php', ['id' => $r['user_id']])) ?>"><?= e($r['customer_name']) ?></a><br><small class="muted">Shown as <?= e($r['reviewer_name']) ?></small></td>
                        <td class="review-cell">
                            <span class="review-stars" aria-label="<?= e((string) $r['rating']) ?> out of 5 stars"><?= str_repeat('★', (int) $r['rating']) ?><span class="review-stars__off"><?= str_repeat('★', 5 - (int) $r['rating']) ?></span></span>
                            <?php if ($r['comment'] !== null && $r['comment'] !== ''): ?><p class="review-cell__text"><?= e($r['comment']) ?></p><?php else: ?><p class="muted">No comment</p><?php endif; ?>
                        </td>
                        <td><span class="badge badge--<?= (int) $r['is_visible'] === 1 ? 'success' : 'warning' ?>"><?= (int) $r['is_visible'] === 1 ? 'Shown' : 'Hidden' ?></span></td>
                        <td class="actions">
                            <form method="post" action="<?= e($here) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $r['id']) ?>">
                                <input type="hidden" name="action" value="<?= (int) $r['is_visible'] === 1 ? 'hide' : 'show' ?>">
                                <button type="submit" class="btn btn--ghost btn--small"><?= (int) $r['is_visible'] === 1 ? 'Hide' : 'Show' ?></button>
                            </form>
                            <form method="post" action="<?= e($here) ?>" data-confirm="Delete this review permanently? The customer can write a new one.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $r['id']) ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn--danger-ghost btn--small">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= View::partial('pagination', ['page' => $result['page'], 'pages' => $result['pages'], 'path' => 'admin/reviews.php', 'params' => $filters]) ?>
    <?php endif; ?>
</section>
