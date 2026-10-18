<?php
/**
 * GiftAura - customer accounts, as seen by administrators.
 * Shows only what is needed to fulfil orders. Passwords are never shown;
 * an administrator can only set a new temporary password.
 * "Total spent" uses the same rule as the sales report: orders that are
 * not cancelled and whose payment is not refunded.
 */
declare(strict_types=1);

final class CustomerAdminService
{
    public const PER_PAGE = 25;

    private const SPENT_SQL = "(SELECT COALESCE(SUM(o.total_amount), 0) FROM orders o
                                 LEFT JOIN payments p ON p.order_id = o.id
                                WHERE o.user_id = u.id AND o.status <> 'cancelled'
                                  AND (p.status IS NULL OR p.status <> 'refunded'))";

    public static function list(array $filters): array
    {
        $where = ["u.role = 'customer'"];
        $params = [];
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']) . '%';
            $where[] = "(u.full_name LIKE :q1 ESCAPE '!' OR u.email LIKE :q2 ESCAPE '!' OR u.phone LIKE :q3 ESCAPE '!')";
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        if (($filters['status'] ?? '') === 'active') {
            $where[] = 'u.is_active = 1';
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $where[] = 'u.is_active = 0';
        }
        $whereSql = implode(' AND ', $where);
        $total = (int) Database::fetchValue("SELECT COUNT(*) FROM users u WHERE {$whereSql}", $params);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, (int) ($filters['page'] ?? 1)), $pages);
        $items = Database::fetchAll(
            "SELECT u.id, u.full_name, u.email, u.phone, u.is_active, u.email_verified_at, u.created_at, u.last_login_at,
                    (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count,
                    " . self::SPENT_SQL . " AS total_spent
               FROM users u
              WHERE {$whereSql}
              ORDER BY u.created_at DESC, u.id DESC
              LIMIT :limit OFFSET :offset",
            $params + ['limit' => self::PER_PAGE, 'offset' => ($page - 1) * self::PER_PAGE]
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public static function find(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT u.id, u.full_name, u.email, u.phone, u.is_active, u.email_verified_at, u.created_at, u.last_login_at,
                    (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count,
                    " . self::SPENT_SQL . " AS total_spent
               FROM users u WHERE u.id = :id AND u.role = 'customer'",
            ['id' => $id]
        );
    }

    public static function orders(int $userId): array
    {
        return Database::fetchAll(
            'SELECT o.id, o.order_number, o.status, o.total_amount, o.created_at, p.method AS payment_method, p.status AS payment_status
               FROM orders o LEFT JOIN payments p ON p.order_id = o.id
              WHERE o.user_id = :uid ORDER BY o.created_at DESC, o.id DESC',
            ['uid' => $userId]
        );
    }

    public static function addresses(int $userId): array
    {
        return AddressService::forUser($userId);
    }

    /** Activate or deactivate a CUSTOMER account (administrators cannot be changed here). */
    public static function setActive(int $userId, bool $active, int $adminId): bool
    {
        $done = Database::execute(
            "UPDATE users SET is_active = :a WHERE id = :id AND role = 'customer'",
            ['a' => $active ? 1 : 0, 'id' => $userId]
        ) > 0;
        if ($done) {
            ActivityLogService::log($adminId, $active ? 'customer.activate' : 'customer.deactivate', 'customer', $userId);
        }
        return $done;
    }

    /**
     * Give a customer a new random temporary password (shown ONCE to the admin,
     * never stored in plain text or logged). Returns the password or null.
     */
    public static function setTemporaryPassword(int $userId, int $adminId): ?string
    {
        if (self::find($userId) === null) {
            return null;
        }
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $password = '';
        for ($i = 0; $i < 10; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $password .= (string) random_int(10, 99); // always contains letters and a number
        Database::execute(
            "UPDATE users SET password_hash = :h WHERE id = :id AND role = 'customer'",
            ['h' => password_hash($password, PASSWORD_DEFAULT), 'id' => $userId]
        );
        ActivityLogService::log($adminId, 'customer.temp_password', 'customer', $userId, 'Temporary password issued');
        return $password;
    }
}
