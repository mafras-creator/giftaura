<?php
/**
 * Pagination links. Variables: $page, $pages, $path (e.g. 'products.php'),
 * $params (current filters to keep in every link).
 */
if ($pages <= 1) {
    return;
}
$link = static fn(int $n): string => url($path, $params + ['page' => $n]);
$start = max(1, $page - 2);
$end = min($pages, $page + 2);
?>
<nav class="pagination" aria-label="Pages">
    <?php if ($page > 1): ?>
        <a class="pagination__link" href="<?= e($link($page - 1)) ?>" rel="prev">&lsaquo; Previous</a>
    <?php endif; ?>

    <?php if ($start > 1): ?>
        <a class="pagination__link" href="<?= e($link(1)) ?>">1</a>
        <?php if ($start > 2): ?><span class="pagination__gap">&hellip;</span><?php endif; ?>
    <?php endif; ?>

    <?php for ($n = $start; $n <= $end; $n++): ?>
        <?php if ($n === $page): ?>
            <span class="pagination__link is-current" aria-current="page"><?= e((string) $n) ?></span>
        <?php else: ?>
            <a class="pagination__link" href="<?= e($link($n)) ?>"><?= e((string) $n) ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($end < $pages): ?>
        <?php if ($end < $pages - 1): ?><span class="pagination__gap">&hellip;</span><?php endif; ?>
        <a class="pagination__link" href="<?= e($link($pages)) ?>"><?= e((string) $pages) ?></a>
    <?php endif; ?>

    <?php if ($page < $pages): ?>
        <a class="pagination__link" href="<?= e($link($page + 1)) ?>" rel="next">Next &rsaquo;</a>
    <?php endif; ?>
</nav>
