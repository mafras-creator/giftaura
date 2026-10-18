<?php
/**
 * GiftAura - product catalogue (read side, used by the shop).
 *
 * Customers only ever see products that are active AND belong to an
 * active category (whose parent, if any, is also active).
 * All filter values are validated or whitelisted before use, and every
 * value reaches the database through a prepared-statement placeholder.
 * Phase 6 adds create/edit/deactivate for administrators.
 */
declare(strict_types=1);

final class ProductService
{
    public const PER_PAGE = 12;

    /** Allowed sort options: key => [label, ORDER BY clause]. Only these keys are accepted. */
    public const SORTS = [
        'newest'     => ['Newest first',        'p.created_at DESC, p.id DESC'],
        'price_asc'  => ['Price: low to high',  'p.price ASC, p.name ASC'],
        'price_desc' => ['Price: high to low',  'p.price DESC, p.name ASC'],
        'name'       => ['Name: A to Z',         'p.name ASC'],
    ];

    /** Columns needed for a product card, plus the image to show. */
    private const CARD_COLUMNS = '
        p.id, p.name, p.slug, p.short_description, p.price, p.compare_at_price, p.stock_quantity, p.low_stock_threshold,
        p.is_featured, p.is_customizable, c.name AS category_name, c.slug AS category_slug,
        (SELECT AVG(r.rating) FROM product_reviews r WHERE r.product_id = p.id AND r.is_visible = 1) AS rating_avg,
        (SELECT COUNT(*) FROM product_reviews r WHERE r.product_id = p.id AND r.is_visible = 1) AS rating_count,
        (SELECT pi.file_name FROM product_images pi
          WHERE pi.product_id = p.id
          ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
          LIMIT 1) AS image';

    /**
     * Search, filter, sort and paginate the catalogue.
     *
     * $filters keys (all optional, already validated by the page):
     *   category  array  category ids to include (a category plus its subcategories)
     *   q         string search words
     *   min, max  string prices in LKR
     *   in_stock  bool   only products with stock
 *   on_sale   bool   only products with a sale price
     *   sort      string key of SORTS
     *   page      int    1-based page number
     *
     * Returns ['items' => [...], 'total' => int, 'page' => int, 'pages' => int].
     */
    public static function search(array $filters): array
    {
        [$where, $params] = self::visibilityWhere();

        if (!empty($filters['category'])) {
            $in = [];
            foreach (array_values($filters['category']) as $i => $id) {
                $in[] = ':fc' . $i;
                $params['fc' . $i] = (int) $id;
            }
            $where[] = 'p.category_id IN (' . implode(', ', $in) . ')';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            // Each word must appear in the name, short description, description or SKU.
            $words = array_slice(preg_split('/\s+/u', $q) ?: [], 0, 5);
            foreach ($words as $i => $word) {
                $like = '%' . self::escapeLike($word) . '%';
                $where[] = "(p.name LIKE :qa{$i} ESCAPE '!' OR p.short_description LIKE :qb{$i} ESCAPE '!'
                             OR p.description LIKE :qc{$i} ESCAPE '!' OR p.sku LIKE :qd{$i} ESCAPE '!')";
                $params["qa{$i}"] = $like;
                $params["qb{$i}"] = $like;
                $params["qc{$i}"] = $like;
                $params["qd{$i}"] = $like;
            }
        }

        if (isset($filters['min']) && $filters['min'] !== '') {
            $where[] = 'p.price >= :min_price';
            $params['min_price'] = (string) $filters['min'];
        }
        if (isset($filters['max']) && $filters['max'] !== '') {
            $where[] = 'p.price <= :max_price';
            $params['max_price'] = (string) $filters['max'];
        }
        if (!empty($filters['in_stock'])) {
            $where[] = 'p.stock_quantity > 0';
        }
        if (!empty($filters['on_sale'])) {
            $where[] = 'p.compare_at_price IS NOT NULL AND p.compare_at_price > p.price';
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id WHERE {$whereSql}",
            $params
        );

        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, (int) ($filters['page'] ?? 1)), $pages);
        $order = self::SORTS[$filters['sort'] ?? 'newest'][1] ?? self::SORTS['newest'][1];

        $items = Database::fetchAll(
            'SELECT ' . self::CARD_COLUMNS . "
               FROM products p
               JOIN categories c ON c.id = p.category_id
              WHERE {$whereSql}
              ORDER BY {$order}
              LIMIT :limit OFFSET :offset",
            $params + ['limit' => self::PER_PAGE, 'offset' => ($page - 1) * self::PER_PAGE]
        );

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** Featured products for the homepage. */
    public static function featured(int $limit = 4): array
    {
        [$where, $params] = self::visibilityWhere();
        return Database::fetchAll(
            'SELECT ' . self::CARD_COLUMNS . '
               FROM products p JOIN categories c ON c.id = p.category_id
              WHERE ' . implode(' AND ', $where) . ' AND p.is_featured = 1
              ORDER BY p.stock_quantity > 0 DESC, p.created_at DESC, p.id DESC
              LIMIT :limit',
            $params + ['limit' => $limit]
        );
    }

    /** Most recently added products. */
    public static function latest(int $limit = 8): array
    {
        [$where, $params] = self::visibilityWhere();
        return Database::fetchAll(
            'SELECT ' . self::CARD_COLUMNS . '
               FROM products p JOIN categories c ON c.id = p.category_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY p.created_at DESC, p.id DESC
              LIMIT :limit',
            $params + ['limit' => $limit]
        );
    }

    /** One visible product by its slug, with category details, or null. */
    public static function findBySlug(string $slug): ?array
    {
        [$where, $params] = self::visibilityWhere();
        $product = Database::fetchOne(
            'SELECT p.id, p.category_id, p.sku, p.name, p.slug, p.short_description, p.description,
                    p.price, p.compare_at_price, p.stock_quantity, p.low_stock_threshold, p.is_featured, p.created_at,
                    p.is_customizable, p.custom_label, p.custom_max_length, p.custom_required,
                    c.name AS category_name, c.slug AS category_slug
               FROM products p JOIN categories c ON c.id = p.category_id
              WHERE ' . implode(' AND ', $where) . ' AND p.slug = :slug',
            $params + ['slug' => $slug]
        );
        if ($product !== null) {
            $product['id'] = (int) $product['id'];
            $product['category_id'] = (int) $product['category_id'];
        }
        return $product;
    }

    /** All images of a product, primary first. */
    public static function images(int $productId): array
    {
        return Database::fetchAll(
            'SELECT id, file_name, alt_text, is_primary
               FROM product_images
              WHERE product_id = :id
              ORDER BY is_primary DESC, sort_order ASC, id ASC',
            ['id' => $productId]
        );
    }

    /** Other visible products from the same category (or its parent's family). */
    public static function related(array $product, int $limit = 4): array
    {
        $category = CategoryService::findById($product['category_id']);
        $rootId = $category['parent_id'] ?? $product['category_id'];
        $familyIds = CategoryService::idsIncludingChildren((int) $rootId);

        [$where, $params] = self::visibilityWhere();
        $in = [];
        foreach ($familyIds as $i => $id) {
            $in[] = ':rc' . $i;
            $params['rc' . $i] = $id;
        }
        return Database::fetchAll(
            'SELECT ' . self::CARD_COLUMNS . '
               FROM products p JOIN categories c ON c.id = p.category_id
              WHERE ' . implode(' AND ', $where) . '
                AND p.category_id IN (' . implode(', ', $in) . ')
                AND p.id <> :exclude
              ORDER BY p.stock_quantity > 0 DESC, p.is_featured DESC, p.created_at DESC
              LIMIT :limit',
            $params + ['exclude' => $product['id'], 'limit' => $limit]
        );
    }

    /** Lowest and highest price of visible products (for filter hints). */
    public static function priceRange(): array
    {
        [$where, $params] = self::visibilityWhere();
        $row = Database::fetchOne(
            'SELECT MIN(p.price) AS min_price, MAX(p.price) AS max_price
               FROM products p JOIN categories c ON c.id = p.category_id
              WHERE ' . implode(' AND ', $where),
            $params
        );
        return ['min' => (float) ($row['min_price'] ?? 0), 'max' => (float) ($row['max_price'] ?? 0)];
    }

    /**
     * WHERE parts shared by every customer-facing query:
     * product active, category visible.
     */
    private static function visibilityWhere(): array
    {
        $ids = CategoryService::visibleIds();
        if ($ids === []) {
            return [['1 = 0'], []];
        }
        $in = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $in[] = ':vc' . $i;
            $params['vc' . $i] = $id;
        }
        return [['p.is_active = 1', 'p.category_id IN (' . implode(', ', $in) . ')'], $params];
    }

    /** Escape LIKE wildcards so a search for "50%" means the text "50%". */
    private static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
