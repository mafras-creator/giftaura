<?php
/**
 * GiftAura - customer wishlist (the heart button on product pictures).
 *
 * Only logged-in customers have a wishlist. A guest who presses the heart
 * is asked to log in; the product is remembered in the session and added
 * as soon as they log in or create an account.
 */
declare(strict_types=1);

final class WishlistService
{
    public const MAX_ITEMS = 100;

    private static ?array $ids = null;

    /** Product ids in the current customer's wishlist (cached for the request). */
    public static function ids(): array
    {
        if (self::$ids === null) {
            $user = current_user();
            self::$ids = $user === null || $user['role'] !== 'customer' ? [] : array_map('intval', array_column(
                Database::fetchAll('SELECT product_id FROM wishlist_items WHERE user_id = :u', ['u' => $user['id']]),
                'product_id'
            ));
        }
        return self::$ids;
    }

    public static function has(int $productId): bool
    {
        return in_array($productId, self::ids(), true);
    }

    public static function count(): int
    {
        return count(self::ids());
    }

    /**
     * Add or remove. Returns ['saved' => bool] (new state) or ['error' => message].
     * Only active products can be added; any product can be removed.
     */
    public static function toggle(int $userId, int $productId): array
    {
        $exists = Database::fetchValue(
            'SELECT 1 FROM wishlist_items WHERE user_id = :u AND product_id = :p', ['u' => $userId, 'p' => $productId]
        ) !== null;
        if ($exists) {
            self::remove($userId, $productId);
            return ['saved' => false];
        }
        return self::add($userId, $productId);
    }

    public static function add(int $userId, int $productId): array
    {
        if (Database::fetchValue('SELECT 1 FROM products WHERE id = :p AND is_active = 1', ['p' => $productId]) === null) {
            return ['error' => 'This product is not available.'];
        }
        if ((int) Database::fetchValue('SELECT COUNT(*) FROM wishlist_items WHERE user_id = :u', ['u' => $userId]) >= self::MAX_ITEMS) {
            return ['error' => 'Your wishlist is full (' . self::MAX_ITEMS . ' products). Remove some first.'];
        }
        try {
            Database::execute('INSERT INTO wishlist_items (user_id, product_id) VALUES (:u, :p)', ['u' => $userId, 'p' => $productId]);
        } catch (PDOException $e) {
            // Already saved (e.g. two clicks at the same moment) - that is fine.
        }
        self::$ids = null;
        return ['saved' => true];
    }

    public static function remove(int $userId, int $productId): void
    {
        Database::execute('DELETE FROM wishlist_items WHERE user_id = :u AND product_id = :p', ['u' => $userId, 'p' => $productId]);
        self::$ids = null;
    }

    /** Products in a customer's wishlist that the shop can show, newest first. */
    public static function items(int $userId): array
    {
        return Database::fetchAll(
            'SELECT p.id, p.name, p.slug, p.short_description, p.price, p.compare_at_price, p.stock_quantity, p.low_stock_threshold,
                    p.is_featured, p.is_customizable, c.name AS category_name, c.slug AS category_slug,
                    (SELECT AVG(r.rating) FROM product_reviews r WHERE r.product_id = p.id AND r.is_visible = 1) AS rating_avg,
                    (SELECT COUNT(*) FROM product_reviews r WHERE r.product_id = p.id AND r.is_visible = 1) AS rating_count,
                    (SELECT pi.file_name FROM product_images pi WHERE pi.product_id = p.id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS image
               FROM wishlist_items w
               JOIN products p ON p.id = w.product_id
               JOIN categories c ON c.id = p.category_id
              WHERE w.user_id = :u AND p.is_active = 1 AND c.is_active = 1
              ORDER BY w.created_at DESC, p.id DESC',
            ['u' => $userId]
        );
    }

    /** Guest pressed the heart: remember the product until they log in. */
    public static function remember(int $productId): void
    {
        $_SESSION['wishlist_pending'] = $productId;
    }

    /** After login / registration: add the remembered product. Returns its name or null. */
    public static function addPending(array $user): ?string
    {
        $productId = $_SESSION['wishlist_pending'] ?? null;
        unset($_SESSION['wishlist_pending']);
        if (!is_int($productId) || $user['role'] !== 'customer') {
            return null;
        }
        if (isset(self::add((int) $user['id'], $productId)['saved'])) {
            $name = Database::fetchValue('SELECT name FROM products WHERE id = :p', ['p' => $productId]);
            return is_string($name) ? $name : null;
        }
        return null;
    }
}
