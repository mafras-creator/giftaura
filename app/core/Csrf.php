<?php
/**
 * GiftAura - Cross-Site Request Forgery (CSRF) protection.
 *
 * Every form that changes data includes a secret token stored in the
 * user's session. A malicious website cannot read this token, so it
 * cannot submit forms on the user's behalf.
 */
declare(strict_types=1);

final class Csrf
{
    private const FIELD = '_csrf';

    /** The current session's token (created on first use). */
    public static function token(): string
    {
        if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    /** Hidden form field containing the token. */
    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . e(self::token()) . '">';
    }

    /** True when the submitted token matches (constant-time comparison). */
    public static function isValid(?string $submitted): bool
    {
        $expected = $_SESSION['_csrf_token'] ?? '';
        return is_string($submitted) && $submitted !== '' && is_string($expected) && $expected !== ''
            && hash_equals($expected, $submitted);
    }

    /**
     * Call at the top of every POST handler. Stops with "Page expired"
     * (HTTP 419) when the token is missing or wrong.
     */
    public static function verify(): void
    {
        $submitted = $_POST[self::FIELD] ?? null;
        if (!self::isValid(is_string($submitted) ? $submitted : null)) {
            Logger::warning('CSRF token rejected', ['url' => $_SERVER['REQUEST_URI'] ?? '', 'ip' => client_ip()]);
            abort(419, 'This form has expired or was sent from another site. Please go back, refresh the page and try again.');
        }
    }
}
