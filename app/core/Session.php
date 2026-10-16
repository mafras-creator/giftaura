<?php
/**
 * GiftAura - hardened PHP session.
 *
 * - Cookie: HttpOnly (JavaScript cannot read it), SameSite=Lax,
 *   Secure when served over HTTPS, limited to the site path.
 * - Strict mode: PHP refuses session IDs it did not create.
 * - Idle timeout: the session ends after N minutes without activity.
 * - regenerate(): new session ID after login/logout (stops session fixation).
 */
declare(strict_types=1);

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_name((string) config('security.session_name', 'GIFTAURA_SID'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => base_path() . '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Idle timeout
        $idleSeconds = (int) config('security.session_idle_minutes', 30) * 60;
        $now = time();
        if (isset($_SESSION['_last_activity']) && ($now - (int) $_SESSION['_last_activity']) > $idleSeconds) {
            $hadUser = isset($_SESSION['user_id']);
            self::destroy();
            self::start();
            if ($hadUser) {
                Flash::add('info', 'You were logged out after a period of inactivity. Please log in again.');
            }
        }
        $_SESSION['_last_activity'] = $now;
    }

    /** Issue a new session ID and delete the old session data file. */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /** Remove all session data and the session cookie. */
    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?: 'Lax',
        ]);
        session_destroy();
    }
}
