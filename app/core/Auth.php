<?php
/**
 * GiftAura - authentication and authorisation.
 *
 * The session stores only the user's id. On every request the user is
 * re-loaded from the database, so a deactivated account or a changed
 * role takes effect immediately. Roles are never taken from the browser.
 *
 * Page guards:
 *   Auth::requireLogin();   any logged-in user (customer or admin)
 *   Auth::requireAdmin();   administrators only (others get 403)
 */
declare(strict_types=1);

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    /** Result codes for attempt(). */
    public const OK = 'ok';
    public const INVALID = 'invalid';
    public const BLOCKED = 'blocked';

    /**
     * Try to log in. Returns Auth::OK, Auth::INVALID or Auth::BLOCKED.
     * The same message is shown for unknown email, wrong password and
     * inactive account, so attackers learn nothing from the response.
     */
    public static function attempt(string $email, string $password): string
    {
        $email = mb_strtolower(trim($email));
        $ip = client_ip();

        if (RateLimiter::isBlocked($email, $ip)) {
            Logger::warning('Login blocked by rate limit', ['email' => $email, 'ip' => $ip]);
            return self::BLOCKED;
        }

        $user = Database::fetchOne(
            'SELECT id, password_hash, is_active FROM users WHERE email = :email',
            ['email' => $email]
        );

        // Always run password_verify (against a dummy hash if the user does not
        // exist) so the response time does not reveal whether an email is registered.
        $hash = $user['password_hash'] ?? '$2y$10$1Z1y7.reg5OpCjwZBuULgeE4XTleG9yGIIYSmbbk6PXXAMKVSevRW';
        $passwordOk = password_verify($password, $hash);

        if ($user === null || !$passwordOk || (int) $user['is_active'] !== 1) {
            RateLimiter::record($email, $ip, false);
            return self::INVALID;
        }

        RateLimiter::record($email, $ip, true);

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::execute('UPDATE users SET password_hash = :h WHERE id = :id', [
                'h' => password_hash($password, PASSWORD_DEFAULT), 'id' => (int) $user['id'],
            ]);
        }

        self::loginById((int) $user['id']);
        Database::execute('UPDATE users SET last_login_at = NOW() WHERE id = :id', ['id' => (int) $user['id']]);
        return self::OK;
    }

    /** Put a user id in the session with a fresh session id (stops session fixation). */
    public static function loginById(int $userId): void
    {
        Session::regenerate();
        $_SESSION['user_id'] = $userId;
        unset($_SESSION['_csrf_token']); // new token for the new login
        self::$loaded = false;
        self::$user = null;
    }

    /** Log out: destroy the whole session, then start a clean one for the flash message. */
    public static function logout(): void
    {
        Session::destroy();
        Session::start();
        Session::regenerate();
        self::$loaded = true;
        self::$user = null;
    }

    /** The logged-in, active user (id, role, full_name, email, phone) or null. */
    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        $id = $_SESSION['user_id'] ?? null;
        if (!is_int($id)) {
            return null;
        }
        $user = Database::fetchOne(
            'SELECT id, role, full_name, email, phone, email_verified_at, created_at, last_login_at
               FROM users WHERE id = :id AND is_active = 1',
            ['id' => $id]
        );
        if ($user === null) {
            // Account removed or deactivated since login.
            unset($_SESSION['user_id']);
            return null;
        }
        $user['id'] = (int) $user['id'];
        self::$user = $user;
        return $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? null) === 'admin';
    }

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    /** Redirect guests to the login page, then back here after login. */
    public static function requireLogin(): array
    {
        $user = self::user();
        if ($user === null) {
            Flash::add('info', 'Please log in to continue.');
            redirect('login.php', ['next' => (string) ($_SERVER['REQUEST_URI'] ?? '')]);
        }
        return $user;
    }

    /** Administrators only. Guests go to login; logged-in non-admins get 403. */
    public static function requireAdmin(): array
    {
        $user = self::requireLogin();
        if ($user['role'] !== 'admin') {
            Logger::warning('Non-admin tried to open an admin page', [
                'user_id' => $user['id'], 'url' => $_SERVER['REQUEST_URI'] ?? '', 'ip' => client_ip(),
            ]);
            abort(403, 'This area is for GiftAura administrators only.');
        }
        return $user;
    }

    /** Pages such as login/register: send logged-in users away. */
    public static function redirectIfLoggedIn(): void
    {
        $user = self::user();
        if ($user !== null) {
            redirect($user['role'] === 'admin' ? 'admin/' : 'account/');
        }
    }
}
