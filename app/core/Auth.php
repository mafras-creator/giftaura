<?php
/**
 * GiftAura - authentication and authorisation.
 *
 * The session stores only the user's id. On every request the user is
 * re-loaded from the database, so a deactivated account or a changed
 * role takes effect immediately. Roles are never taken from the browser.
 *
 * Roles: customer, staff (staff administrator) and admin (super administrator).
 *
 * Page guards:
 *   Auth::requireLogin();              any logged-in user
 *   Auth::requireAdmin();              super administrator or any staff member
 *   Auth::requireAdmin('products');    super administrator, or staff with that permission
 *   Auth::requireSuperAdmin();         super administrator only
 * Others get 403; guests go to the login page. A staff member who must change
 * the generated password is sent to admin/password.php first.
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
        // $email is the e-mail address, or the username of a staff member.
        $email = mb_strtolower(trim($email));
        $ip = client_ip();

        if (RateLimiter::isBlocked($email, $ip)) {
            Logger::warning('Login blocked by rate limit', ['email' => $email, 'ip' => $ip]);
            return self::BLOCKED;
        }

        $user = str_contains($email, '@')
            ? Database::fetchOne('SELECT id, password_hash, is_active FROM users WHERE email = :email', ['email' => $email])
            : Database::fetchOne("SELECT id, password_hash, is_active FROM users WHERE username = :u AND role = 'staff'", ['u' => $email]);

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
            'SELECT id, role, full_name, email, username, phone, permissions, must_change_password,
                    email_verified_at, created_at, last_login_at
               FROM users WHERE id = :id AND is_active = 1',
            ['id' => $id]
        );
        if ($user === null) {
            // Account removed or deactivated since login.
            unset($_SESSION['user_id']);
            return null;
        }
        $user['id'] = (int) $user['id'];
        $user['permission_list'] = $user['role'] === 'staff' ? StaffService::cleanPermissions($user['permissions']) : [];
        self::$user = $user;
        return $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** Super administrator or staff member (may open the admin panel). */
    public static function isAdmin(): bool
    {
        return self::isPanelRole(self::user()['role'] ?? null);
    }

    public static function isPanelRole(?string $role): bool
    {
        return $role === 'admin' || $role === 'staff';
    }

    public static function isSuperAdmin(): bool
    {
        return (self::user()['role'] ?? null) === 'admin';
    }

    /** May the current user work in this admin area? The super administrator may do everything. */
    public static function can(string $permission): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }
        if ($user['role'] === 'admin') {
            return true;
        }
        return $user['role'] === 'staff' && in_array($permission, $user['permission_list'], true);
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

    /**
     * Admin panel pages. Guests go to login; customers get 403. With a
     * $permission, staff members without that permission also get 403.
     */
    public static function requireAdmin(?string $permission = null, bool $allowPasswordChange = false): array
    {
        $user = self::requireLogin();
        if (!self::isPanelRole($user['role'])) {
            Logger::warning('Non-admin tried to open an admin page', [
                'user_id' => $user['id'], 'url' => $_SERVER['REQUEST_URI'] ?? '', 'ip' => client_ip(),
            ]);
            abort(403, 'This area is for GiftAura administrators only.');
        }
        if (!$allowPasswordChange && (int) $user['must_change_password'] === 1) {
            Flash::add('info', 'Please choose your own password before you continue.');
            redirect('admin/password.php');
        }
        if ($permission !== null && !self::can($permission)) {
            Logger::warning('Staff member opened an area without permission', [
                'user_id' => $user['id'], 'permission' => $permission, 'url' => $_SERVER['REQUEST_URI'] ?? '',
            ]);
            abort(403, 'Your staff account does not have access to this area. Ask the main administrator if you need it.');
        }
        return $user;
    }

    /** Super administrator only (staff accounts, settings, logs). */
    public static function requireSuperAdmin(): array
    {
        $user = self::requireAdmin();
        if ($user['role'] !== 'admin') {
            Logger::warning('Staff member opened a super-administrator page', ['user_id' => $user['id'], 'url' => $_SERVER['REQUEST_URI'] ?? '']);
            abort(403, 'Only the main administrator can open this page.');
        }
        return $user;
    }

    /** Pages such as login/register: send logged-in users away. */
    public static function redirectIfLoggedIn(): void
    {
        $user = self::user();
        if ($user !== null) {
            redirect(self::isPanelRole($user['role']) ? 'admin/' : 'account/');
        }
    }
}
