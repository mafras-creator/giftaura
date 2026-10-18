<?php
/**
 * GiftAura - product reviews (star rating + comment).
 *
 * Rules:
 *   - Only a customer who RECEIVED the product may review it: they must
 *     have an order with status "delivered" that contains the product.
 *     This is checked again every time a review is saved.
 *   - One review per customer per product; the customer can edit or
 *     delete it. The review is shown at once; the administrator can hide
 *     it (Admin -> Reviews) or delete it.
 *   - The name shown is the first name and the first letter of the last
 *     name ("Nimal P."); the e-mail and phone are never shown.
 *   - Comments are cleaned like personalisation messages and always
 *     printed with e(), so HTML typed by a customer is shown as text.
 */
declare(strict_types=1);

final class ReviewService
{
    public const COMMENT_MAX = 1000;
    public const PER_PAGE = 5;
    public const ADMIN_PER_PAGE = 25;

    // -----------------------------------------------------------------
    // Customer side
    // -----------------------------------------------------------------

    /**
     * Products the customer received (delivered orders), newest first,
     * each with the customer's review if there is one.
     */
    public static function reviewableProducts(int $userId): array
    {
        return Database::fetchAll(
            "SELECT p.id AS product_id, p.name, p.slug, p.is_active,
                    MAX(o.created_at) AS ordered_at,
                    (SELECT pi.file_name FROM product_images pi WHERE pi.product_id = p.id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS image,
                    r.id AS review_id, r.rating, r.comment, r.is_visible, r.updated_at AS reviewed_at
               FROM orders o
               JOIN order_items oi ON oi.order_id = o.id
               JOIN products p ON p.id = oi.product_id
               LEFT JOIN product_reviews r ON r.product_id = p.id AND r.user_id = o.user_id
              WHERE o.user_id = :uid AND o.status = 'delivered'
              GROUP BY p.id, p.name, p.slug, p.is_active, r.id, r.rating, r.comment, r.is_visible, r.updated_at
              ORDER BY (r.id IS NULL) DESC, MAX(o.created_at) DESC, p.id DESC",
            ['uid' => $userId]
        );
    }

    /** The newest delivered order of this customer that contains the product, or null. */
    public static function deliveredOrderId(int $userId, int $productId): ?int
    {
        $id = Database::fetchValue(
            "SELECT o.id FROM orders o JOIN order_items oi ON oi.order_id = o.id
              WHERE o.user_id = :uid AND oi.product_id = :pid AND o.status = 'delivered'
              ORDER BY o.created_at DESC, o.id DESC LIMIT 1",
            ['uid' => $userId, 'pid' => $productId]
        );
        return $id !== null ? (int) $id : null;
    }

    /** Product ids from this order that the customer has already reviewed. */
    public static function reviewedIds(int $userId, array $productIds): array
    {
        $productIds = array_values(array_filter(array_map('intval', $productIds)));
        if ($productIds === []) {
            return [];
        }
        $in = [];
        $params = ['uid' => $userId];
        foreach ($productIds as $i => $id) {
            $in[] = ':p' . $i;
            $params['p' . $i] = $id;
        }
        $rows = Database::fetchAll(
            'SELECT product_id FROM product_reviews WHERE user_id = :uid AND product_id IN (' . implode(', ', $in) . ')',
            $params
        );
        return array_map('intval', array_column($rows, 'product_id'));
    }

    public static function findForUser(int $userId, int $productId): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM product_reviews WHERE user_id = :uid AND product_id = :pid',
            ['uid' => $userId, 'pid' => $productId]
        );
    }

    /**
     * Create or update the customer's review. Returns [] on success or
     * [field => message]. The 'general' key is used when the customer may
     * not review this product.
     */
    public static function save(array $user, int $productId, array $input): array
    {
        $userId = (int) $user['id'];
        if ($user['role'] !== 'customer') {
            return ['general' => 'Only customers can write reviews.'];
        }
        $orderId = self::deliveredOrderId($userId, $productId);
        if ($orderId === null) {
            return ['general' => 'You can review a product after it has been delivered to you.'];
        }

        $errors = [];
        $rating = trim((string) ($input['rating'] ?? ''));
        if (!preg_match('/^[1-5]$/', $rating)) {
            $errors['rating'] = 'Choose 1 to 5 stars.';
        }
        $comment = CartService::cleanMessage((string) ($input['comment'] ?? ''));
        if (mb_strlen($comment) > self::COMMENT_MAX) {
            $errors['comment'] = 'Please use up to ' . self::COMMENT_MAX . ' characters (you typed ' . mb_strlen($comment) . ').';
        }
        if ($errors !== []) {
            return $errors;
        }

        $data = [
            'rating'  => (int) $rating,
            'comment' => $comment !== '' ? $comment : null,
            'name'    => self::displayName((string) $user['full_name']),
            'oid'     => $orderId,
        ];
        $existing = self::findForUser($userId, $productId);
        if ($existing === null) {
            try {
                Database::execute(
                    'INSERT INTO product_reviews (product_id, user_id, order_id, rating, comment, reviewer_name)
                     VALUES (:pid, :uid, :oid, :rating, :comment, :name)',
                    $data + ['pid' => $productId, 'uid' => $userId]
                );
            } catch (PDOException $e) {
                // Two clicks at the same moment: the unique key keeps one review.
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
            }
        } else {
            // An edit keeps the administrator's decision to hide a review.
            Database::execute(
                'UPDATE product_reviews SET rating = :rating, comment = :comment, reviewer_name = :name, order_id = :oid
                  WHERE id = :id',
                $data + ['id' => (int) $existing['id']]
            );
        }
        return [];
    }

    public static function deleteOwn(int $userId, int $productId): bool
    {
        return Database::execute(
            'DELETE FROM product_reviews WHERE user_id = :uid AND product_id = :pid',
            ['uid' => $userId, 'pid' => $productId]
        ) > 0;
    }

    /** "Nimal Perera" -> "Nimal P."; one word stays as it is. */
    public static function displayName(string $fullName): string
    {
        $parts = preg_split('/\s+/u', trim($fullName)) ?: [];
        $parts = array_values(array_filter($parts, static fn($p) => $p !== ''));
        if ($parts === []) {
            return 'Customer';
        }
        $name = mb_substr($parts[0], 0, 40);
        if (count($parts) > 1) {
            $name .= ' ' . mb_strtoupper(mb_substr($parts[count($parts) - 1], 0, 1)) . '.';
        }
        return $name;
    }

    // -----------------------------------------------------------------
    // Shop side
    // -----------------------------------------------------------------

    /** ['average' => 4.5, 'count' => 2, 'stars' => [5 => 1, 4 => 0, ...]] for visible reviews. */
    public static function summary(int $productId): array
    {
        $rows = Database::fetchAll(
            'SELECT rating, COUNT(*) AS n FROM product_reviews WHERE product_id = :pid AND is_visible = 1 GROUP BY rating',
            ['pid' => $productId]
        );
        $stars = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $count = 0;
        $sum = 0;
        foreach ($rows as $row) {
            $r = (int) $row['rating'];
            $n = (int) $row['n'];
            $stars[$r] = $n;
            $count += $n;
            $sum += $r * $n;
        }
        return ['average' => $count > 0 ? round($sum / $count, 1) : 0.0, 'count' => $count, 'stars' => $stars];
    }

    /** Visible reviews of a product, newest first, paginated. */
    public static function forProduct(int $productId, int $page = 1): array
    {
        $total = (int) Database::fetchValue(
            'SELECT COUNT(*) FROM product_reviews WHERE product_id = :pid AND is_visible = 1', ['pid' => $productId]
        );
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $page), $pages);
        $items = Database::fetchAll(
            'SELECT id, rating, comment, reviewer_name, created_at, updated_at
               FROM product_reviews WHERE product_id = :pid AND is_visible = 1
              ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset',
            ['pid' => $productId, 'limit' => self::PER_PAGE, 'offset' => ($page - 1) * self::PER_PAGE]
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    // -----------------------------------------------------------------
    // Administration
    // -----------------------------------------------------------------

    /** Filters: status (visible|hidden), rating (1-5), q (product or customer), page. */
    public static function adminList(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($filters['status'] ?? '') === 'visible') {
            $where[] = 'r.is_visible = 1';
        } elseif (($filters['status'] ?? '') === 'hidden') {
            $where[] = 'r.is_visible = 0';
        }
        if (preg_match('/^[1-5]$/', (string) ($filters['rating'] ?? ''))) {
            $where[] = 'r.rating = :rating';
            $params['rating'] = (int) $filters['rating'];
        }
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) $filters['q']) . '%';
            $where[] = "(p.name LIKE :q1 ESCAPE '!' OR u.full_name LIKE :q2 ESCAPE '!' OR u.email LIKE :q3 ESCAPE '!')";
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        $whereSql = implode(' AND ', $where);
        $from = 'FROM product_reviews r JOIN products p ON p.id = r.product_id JOIN users u ON u.id = r.user_id';
        $total = (int) Database::fetchValue("SELECT COUNT(*) {$from} WHERE {$whereSql}", $params);
        $pages = max(1, (int) ceil($total / self::ADMIN_PER_PAGE));
        $page = min(max(1, (int) ($filters['page'] ?? 1)), $pages);
        $items = Database::fetchAll(
            "SELECT r.*, p.name AS product_name, p.slug AS product_slug, u.full_name AS customer_name, u.email AS customer_email,
                    o.order_number
               {$from} LEFT JOIN orders o ON o.id = r.order_id
              WHERE {$whereSql}
              ORDER BY r.created_at DESC, r.id DESC
              LIMIT :limit OFFSET :offset",
            $params + ['limit' => self::ADMIN_PER_PAGE, 'offset' => ($page - 1) * self::ADMIN_PER_PAGE]
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public static function find(int $id): ?array
    {
        return Database::fetchOne(
            'SELECT r.*, p.name AS product_name FROM product_reviews r JOIN products p ON p.id = r.product_id WHERE r.id = :id',
            ['id' => $id]
        );
    }

    public static function setVisible(int $id, bool $visible, int $adminId): bool
    {
        $review = self::find($id);
        if ($review === null) {
            return false;
        }
        Database::execute('UPDATE product_reviews SET is_visible = :v WHERE id = :id', ['v' => $visible ? 1 : 0, 'id' => $id]);
        ActivityLogService::log($adminId, $visible ? 'review.show' : 'review.hide', 'review', $id,
            $review['rating'] . '★ on ' . $review['product_name'] . ' by ' . $review['reviewer_name']);
        return true;
    }

    public static function delete(int $id, int $adminId): bool
    {
        $review = self::find($id);
        if ($review === null) {
            return false;
        }
        Database::execute('DELETE FROM product_reviews WHERE id = :id', ['id' => $id]);
        ActivityLogService::log($adminId, 'review.delete', 'review', $id,
            $review['rating'] . '★ on ' . $review['product_name'] . ' by ' . $review['reviewer_name']);
        return true;
    }
}
