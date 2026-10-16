<?php
/**
 * GiftAura - figures for the admin dashboard, read live from the database.
 * Sales figures use the same definitions as ReportService (gross sales =
 * orders not cancelled whose payment is not refunded).
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
                                                 AND stock_quantity <= low_stock_threshold)           AS low_stock,
                (SELECT COUNT(*) FROM orders WHERE status = 'pending')                                AS pending_orders,
                (SELECT COUNT(*) FROM orders WHERE status IN ('confirmed','processing','ready_for_delivery')) AS open_orders,
                (SELECT COUNT(*) FROM payments WHERE status = 'awaiting_verification')                AS awaiting_payments"
        );
        $summary = array_map('intval', $row ?? []);
        $today = ReportService::summary(date('Y-m-d'), date('Y-m-d'));
        $month = ReportService::summary(date('Y-m-01'), date('Y-m-d'));
        $summary['sales_today'] = $today['gross_sales'];
        $summary['orders_today'] = $today['orders_all'];
        $summary['sales_month'] = $month['gross_sales'];
        $summary['orders_month'] = $month['orders_counted'];
        $summary['collected_month'] = $month['collected'];
        return $summary;
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

    public static function recentOrders(int $limit = 8): array
    {
        return Database::fetchAll(
            'SELECT o.id, o.order_number, o.customer_name, o.status, o.total_amount, o.created_at,
                    p.method AS payment_method, p.status AS payment_status
               FROM orders o LEFT JOIN payments p ON p.order_id = o.id
              ORDER BY o.created_at DESC, o.id DESC LIMIT :limit',
            ['limit' => $limit]
        );
    }
}
