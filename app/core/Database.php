<?php
/**
 * GiftAura - database access (PDO, MariaDB).
 *
 * - One shared connection per request, opened only when first needed.
 * - Real prepared statements (emulation OFF), so values are never
 *   mixed into SQL text: protects against SQL injection.
 * - Integers are bound as integers (needed for LIMIT/OFFSET).
 * - Session time zone +05:30 so CURRENT_TIMESTAMP is Sri Lankan time.
 *
 * Note: with native prepares a named placeholder may appear only ONCE
 * in a statement. Use :from1 / :from2 if the same value is needed twice.
 */
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                config('db.host', '127.0.0.1'),
                (int) config('db.port', 3306),
                config('db.name', 'giftaura_db')
            );
            try {
                self::$pdo = new PDO($dsn, (string) config('db.user'), (string) config('db.password'), [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                ]);
            } catch (PDOException $e) {
                // Never let the DSN, user name or password reach the browser.
                throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0);
            }
            $offset = (new DateTime('now', new DateTimeZone((string) config('app.timezone', 'Asia/Colombo'))))->format('P');
            self::$pdo->exec("SET time_zone = '" . $offset . "'");
        }
        return self::$pdo;
    }

    /** Prepare, bind and execute a statement. */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with((string) $key, ':') ? $key : ':' . $key);
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    /** First row or null. */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** All rows. */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /** First column of the first row, or null. */
    public static function fetchValue(string $sql, array $params = []): mixed
    {
        $value = self::query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** Run INSERT/UPDATE/DELETE, return affected row count. */
    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    /** Run an INSERT and return the new id. */
    public static function insert(string $sql, array $params = []): int
    {
        self::query($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * Run $work inside a transaction. Commits if it returns normally,
     * rolls back and re-throws if anything fails.
     */
    public static function transaction(callable $work): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $work($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
