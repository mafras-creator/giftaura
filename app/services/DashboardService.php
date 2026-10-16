<?php
/**
 * GiftAura - figures for the admin dashboard, read live from the database.
 * Phase 3: catalogue, customer and stock figures.
 * Phase 6 adds sales and order figures (orders exist from Phase 5).
 */
declare(strict_types=1);

final class DashboardService
{
    public static function summary(): array
    {
        $row = Database::fetchOne(
            "SELECT
                (SELECT COUNT(*) FROM products WHERE is_active = 1)                                   AS active_products,
                (SELECT COUNT(*) FROM products WHERE is_active = 0)                                   AS inactive_products,
                (SELECT COUNT(*) FROM categories WHERE is_active = 1)                                 AS active_categories,
                (SELECT COUNT(*) FROM users WHERE role = 'customer' AND is_active = 1)                AS customers,
                (SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock_quantity = 0)            AS out_of_stock,
                (SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock_quantity > 0
                                                 AND stock_quantity <= low_stock_threshold)           AS low_stock"
        );
        return array_map('intval', $row ?? []);
    }

    /** Active products at or below their low-stock level, lowest stock first. */
    public static function lowStockProducts(int $limit = 10): array
    {
        return Database::fetchAll(
            'SELECT p.id, p.name, p.sku, p.stock_quantity, p.low_stock_threshold, c.name AS category_name
               FROM products p
               JOIN categories c ON c.id = p.category_id
              WHERE p.is_active = 1 AND p.stock_quantity <= p.low_stock_threshold
              ORDER BY p.stock_quantity ASC, p.name ASC
              LIMIT :limit',
            ['limit' => $limit]
        );
    }
}
