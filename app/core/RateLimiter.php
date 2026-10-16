<?php
/**
 * GiftAura - login rate limiting (brute-force protection).
 *
 * A login is blocked when, within the last N minutes (default 15):
 *   - an email address has 5 or more failed attempts since its last
 *     successful login, OR
 *   - an IP address has 20 or more failed attempts.
 * Every attempt is recorded in login_attempts, even for unknown emails,
 * so attackers cannot find out which emails are registered.
 */
declare(strict_types=1);

final class RateLimiter
{
    public static function isBlocked(string $email, string $ip): bool
    {
        $window = (int) config('security.login_window_minutes', 15);
        $since = date('Y-m-d H:i:s', time() - $window * 60);

        $lastSuccess = Database::fetchValue(
            'SELECT MAX(attempted_at) FROM login_attempts WHERE email = :email AND succeeded = 1',
            ['email' => $email]
        );
        $emailSince = ($lastSuccess !== null && $lastSuccess > $since) ? $lastSuccess : $since;

        $emailFailures = (int) Database::fetchValue(
            'SELECT COUNT(*) FROM login_attempts WHERE email = :email AND succeeded = 0 AND attempted_at > :since',
            ['email' => $email, 'since' => $emailSince]
        );
        if ($emailFailures >= (int) config('security.login_max_per_email', 5)) {
            return true;
        }

        $ipFailures = (int) Database::fetchValue(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_address = :ip AND succeeded = 0 AND attempted_at > :since',
            ['ip' => $ip, 'since' => $since]
        );
        return $ipFailures >= (int) config('security.login_max_per_ip', 20);
    }

    public static function record(string $email, string $ip, bool $succeeded): void
    {
        Database::execute(
            'INSERT INTO login_attempts (email, ip_address, succeeded) VALUES (:email, :ip, :ok)',
            ['email' => mb_substr($email, 0, 190), 'ip' => $ip, 'ok' => $succeeded ? 1 : 0]
        );
        // Occasionally remove records older than 7 days to keep the table small.
        if (random_int(1, 50) === 1) {
            Database::execute('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 7 DAY');
        }
    }
}
