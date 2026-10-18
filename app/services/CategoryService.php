<?php
/**
 * GiftAura - product categories (read side, used by the shop).
 * Categories can have one level of subcategories (parent_id).
 * Only active categories are shown to customers.
 * Phase 6 adds create/edit/deactivate for administrators.
 */
declare(strict_types=1);

final class CategoryService
{
    private static ?array $all = null;

    /** All active categories, with the number of active products in each (own products only). */
    private static function allActive(): array
    {
        if (self::$all === null) {
            self::$all = Database::fetchAll(
                'SELECT c.id, c.parent_id, c.name, c.slug, c.description, c.image_file, c.sort_order,
                        (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) AS product_count
                   FROM categories c
                  WHERE c.is_active = 1
                  ORDER BY c.sort_order, c.name'
            );
            foreach (self::$all as &$row) {
                $row['id'] = (int) $row['id'];
                $row['parent_id'] = $row['parent_id'] === null ? null : (int) $row['parent_id'];
                $row['product_count'] = (int) $row['product_count'];
            }
            unset($row);
        }
        return self::$all;
    }

    /** Active top-level categories in display order. */
    public static function topLevel(): array
    {
        return array_values(array_filter(self::allActive(), static fn(array $c): bool => $c['parent_id'] === null));
    }

    /**
     * Tree for navigation: top-level categories, each with 'children' and
     * 'total_count' (its own products plus its subcategories' products).
     * A subcategory whose parent is inactive is hidden.
     */
    public static function tree(bool $withPictures = false): array
    {
        $tree = [];
        foreach (self::topLevel() as $parent) {
            $parent['children'] = array_values(array_filter(
                self::allActive(),
                static fn(array $c): bool => $c['parent_id'] === $parent['id']
            ));
            $parent['total_count'] = $parent['product_count']
                + array_sum(array_column($parent['children'], 'product_count'));
            if ($withPictures) {
                $parent['picture'] = self::picture($parent);
            }
            $tree[] = $parent;
        }
        return $tree;
    }

    /** Active category by slug (its parent must also be active), or null. */
    public static function findBySlug(string $slug): ?array
    {
        foreach (self::allActive() as $category) {
            if ($category['slug'] === $slug) {
                if ($category['parent_id'] !== null && self::findById($category['parent_id']) === null) {
                    return null;
                }
                return $category;
            }
        }
        return null;
    }

    public static function findById(int $id): ?array
    {
        foreach (self::allActive() as $category) {
            if ($category['id'] === $id) {
                return $category;
            }
        }
        return null;
    }

    /** The category id plus the ids of its active subcategories. */
    public static function idsIncludingChildren(int $categoryId): array
    {
        $ids = [$categoryId];
        foreach (self::allActive() as $category) {
            if ($category['parent_id'] === $categoryId) {
                $ids[] = $category['id'];
            }
        }
        return $ids;
    }

    /** Ids of every category a customer may see (active, with an active parent). */
    public static function visibleIds(): array
    {
        $ids = [];
        foreach (self::allActive() as $category) {
            if ($category['parent_id'] === null || self::findById($category['parent_id']) !== null) {
                $ids[] = $category['id'];
            }
        }
        return $ids;
    }

    /** Breadcrumb trail for a category: [parent?, category]. */
    public static function trail(array $category): array
    {
        $trail = [$category];
        if ($category['parent_id'] !== null) {
            $parent = self::findById($category['parent_id']);
            if ($parent !== null) {
                array_unshift($trail, $parent);
            }
        }
        return $trail;
    }

    /**
     * Picture for a top-level category: its own uploaded picture, otherwise
     * the main photo of one of its (or its subcategories') active products,
     * otherwise the placeholder.
     */
    public static function picture(array $category): string
    {
        $own = ImageUploader::url($category['image_file'] ?? null, 'categories');
        if ($own !== null) {
            return $own;
        }
        $ids = self::idsIncludingChildren((int) $category['id']);
        $params = [];
        $in = [];
        foreach ($ids as $i => $id) {
            $in[] = ':c' . $i;
            $params['c' . $i] = $id;
        }
        $file = Database::fetchValue(
            'SELECT pi.file_name FROM products p JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
              WHERE p.is_active = 1 AND p.category_id IN (' . implode(', ', $in) . ')
              ORDER BY p.is_featured DESC, p.stock_quantity > 0 DESC, p.id
              LIMIT 1',
            $params
        );
        return product_image_url(is_string($file) ? $file : null);
    }
}
