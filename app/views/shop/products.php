<?php
/**
 * Product listing with category navigation, search and filters.
 * Variables: $heading, $category, $trail, $tree, $q, $min, $max, $inStock,
 *            $sort, $result, $filters, $priceRange, $notices.
 */
$total = $result['total'];
$activeSlug = $category['slug'] ?? '';
?>
<div class="page-head">
    <div class="container">
        <nav class="breadcrumbs" aria-label="Breadcrumb">
            <a href="<?= e(url()) ?>">Home</a>
            <span aria-hidden="true">/</span>
            <?php if ($trail === []): ?>
                <span aria-current="page">Shop</span>
            <?php else: ?>
                <a href="<?= e(url('products.php')) ?>">Shop</a>
                <?php foreach ($trail as $i => $crumb): ?>
                    <span aria-hidden="true">/</span>
                    <?php if ($i === array_key_last($trail)): ?>
                        <span aria-current="page"><?= e($crumb['name']) ?></span>
                    <?php else: ?>
                        <a href="<?= e(url('products.php', ['category' => $crumb['slug']])) ?>"><?= e($crumb['name']) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </nav>
        <h1 class="page-title"><?= e($heading) ?></h1>
        <?php if (!empty($category['description'])): ?>
            <p class="page-lead"><?= e($category['description']) ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="container shop-layout">
    <aside class="shop-sidebar" aria-label="Filters">
        <button class="btn btn--ghost btn--block filters-toggle" type="button" aria-expanded="false" aria-controls="shop-filters" data-filters-toggle>
            Categories &amp; filters
        </button>

        <div class="shop-filters" id="shop-filters">
            <section class="filter-block">
                <h2 class="filter-block__title">Categories</h2>
                <ul class="category-menu">
                    <li><a class="category-menu__link<?= $activeSlug === '' ? ' is-active' : '' ?>" href="<?= e(url('products.php', array_diff_key($filters, ['category' => 1, 'page' => 1]))) ?>">All products</a></li>
                    <?php foreach ($tree as $node): ?>
                        <li>
                            <a class="category-menu__link<?= $activeSlug === $node['slug'] ? ' is-active' : '' ?>"
                               href="<?= e(url('products.php', ['category' => $node['slug']] + array_diff_key($filters, ['category' => 1]))) ?>">
                                <?= e($node['name']) ?> <span class="count"><?= e((string) $node['total_count']) ?></span>
                            </a>
                            <?php if ($node['children'] !== []): ?>
                                <ul class="category-menu__sub">
                                    <?php foreach ($node['children'] as $child): ?>
                                        <li>
                                            <a class="category-menu__link<?= $activeSlug === $child['slug'] ? ' is-active' : '' ?>"
                                               href="<?= e(url('products.php', ['category' => $child['slug']] + array_diff_key($filters, ['category' => 1]))) ?>">
                                                <?= e($child['name']) ?> <span class="count"><?= e((string) $child['product_count']) ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <form class="filter-block" method="get" action="<?= e(url('products.php')) ?>">
                <h2 class="filter-block__title">Filter</h2>
                <?php if ($activeSlug !== ''): ?>
                    <input type="hidden" name="category" value="<?= e($activeSlug) ?>">
                <?php endif; ?>
                <input type="hidden" name="sort" value="<?= e($sort) ?>">

                <div class="form-field">
                    <label for="filter-q">Search</label>
                    <input type="search" id="filter-q" name="q" value="<?= e($q) ?>" maxlength="100" placeholder="e.g. roses">
                </div>

                <fieldset class="price-fields">
                    <legend>Price (LKR)</legend>
                    <div class="price-fields__row">
                        <label class="sr-only" for="filter-min">Minimum price</label>
                        <input type="number" id="filter-min" name="min" value="<?= e($min) ?>" min="0" step="0.01" inputmode="decimal"
                               placeholder="<?= e(number_format($priceRange['min'], 0, '.', '')) ?>">
                        <span aria-hidden="true">to</span>
                        <label class="sr-only" for="filter-max">Maximum price</label>
                        <input type="number" id="filter-max" name="max" value="<?= e($max) ?>" min="0" step="0.01" inputmode="decimal"
                               placeholder="<?= e(number_format($priceRange['max'], 0, '.', '')) ?>">
                    </div>
                </fieldset>

                <label class="checkbox">
                    <input type="checkbox" name="in_stock" value="1"<?= $inStock ? ' checked' : '' ?>>
                    <span>In stock only</span>
                </label>

                <button type="submit" class="btn btn--primary btn--block">Apply filters</button>
                <?php if (array_diff_key($filters, ['category' => 1, 'sort' => 1]) !== []): ?>
                    <a class="clear-link" href="<?= e(url('products.php', array_intersect_key($filters, ['category' => 1]))) ?>">Clear filters</a>
                <?php endif; ?>
            </form>
        </div>
    </aside>

    <section class="shop-results" aria-live="polite">
        <?php foreach ($notices as $notice): ?>
            <div class="alert alert--warning"><span><?= e($notice) ?></span></div>
        <?php endforeach; ?>

        <div class="results-bar">
            <p class="results-bar__count">
                <?= e((string) $total) ?> <?= $total === 1 ? 'product' : 'products' ?>
                <?php if ($q !== ''): ?> for &ldquo;<?= e($q) ?>&rdquo;<?php endif; ?>
            </p>
            <form method="get" action="<?= e(url('products.php')) ?>" class="sort-form">
                <?php foreach (array_diff_key($filters, ['sort' => 1]) as $key => $value): ?>
                    <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
                <?php endforeach; ?>
                <label for="sort">Sort by</label>
                <select id="sort" name="sort" data-auto-submit>
                    <?php foreach (ProductService::SORTS as $key => [$label]): ?>
                        <option value="<?= e($key) ?>"<?= $key === $sort ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <noscript><button type="submit" class="btn btn--ghost btn--small">Sort</button></noscript>
            </form>
        </div>

        <?php if ($result['items'] === []): ?>
            <div class="empty-state">
                <p><strong>No products match your search.</strong></p>
                <p>Try different words, widen the price range or <a href="<?= e(url('products.php')) ?>">view all products</a>.</p>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($result['items'] as $product): ?>
                    <?= View::partial('product-card', ['product' => $product]) ?>
                <?php endforeach; ?>
            </div>
            <?= View::partial('pagination', [
                'page' => $result['page'], 'pages' => $result['pages'],
                'path' => 'products.php', 'params' => $filters,
            ]) ?>
        <?php endif; ?>
    </section>
</div>
