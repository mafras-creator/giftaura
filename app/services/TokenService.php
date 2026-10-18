<?php
/**
 * GiftAura - one-time secret links (e-mail verification, password reset).
 *
 * - The secret is 32 random bytes (64 hex characters) and is only sent in
 *   the e-mail. The database stores its SHA-256 hash, so someone who can
 *   read the database cannot use the links.
 * - Each link expires (verification 24 h, password reset 60 min) and can
 *   be used only once. Creating a new link cancels the older unused ones.
 * - Using a link marks it used with a conditional UPDATE, so two clicks at
 *   the same moment cannot both succeed.
 */
declare(strict_types=1);

final class TokenService
{
    public const VERIFY = 'verify_email';
    public const RESET = 'reset_password';

    private const TTL_MINUTES = [self::VERIFY => 24 * 60, self::RESET => 60];
    private const MAX_PER_HOUR = 3;

    /** Create a new link secret for a user. Returns the plain secret (to put in the e-mail). */
    public static function create(int $userId, string $purpose): string
    {
        self::assertPurpose($purpose);
        $secret = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        Database::transaction(static function () use ($userId, $purpose, $secret, $now): void {
            // Older unused links for the same purpose stop working.
            Database::execute(
                'UPDATE user_tokens SET used_at = :now WHERE user_id = :uid AND purpose = :p AND used_at IS NULL',
                ['now' => $now, 'uid' => $userId, 'p' => $purpose]
            );
            Database::execute(
                'INSERT INTO user_tokens (user_id, purpose, token_hash, expires_at, created_at)
                 VALUES (:uid, :p, :hash, :expires, :created)',
                [
                    'uid' => $userId, 'p' => $purpose, 'hash' => hash('sha256', $secret),
                    'expires' => date('Y-m-d H:i:s', time() + self::TTL_MINUTES[$purpose] * 60),
                    'created' => $now,
                ]
            );
        });
        return $secret;
    }

    /** True if the user already received the maximum number of links in the last hour. */
    public static function tooMany(int $userId, string $purpose): bool
    {
        return (int) Database::fetchValue(
            'SELECT COUNT(*) FROM user_tokens WHERE user_id = :uid AND purpose = :p AND created_at > :since',
            ['uid' => $userId, 'p' => $purpose, 'since' => date('Y-m-d H:i:s', time() - 3600)]
        ) >= self::MAX_PER_HOUR;
    }

    /** The user id for a valid (unused, not expired) secret, or null. Does not use it up. */
    public static function peek(string $secret, string $purpose): ?int
    {
        if (!self::looksValid($secret)) {
            return null;
        }
        $id = Database::fetchValue(
            'SELECT t.user_id FROM user_tokens t JOIN users u ON u.id = t.user_id
              WHERE t.token_hash = :hash AND t.purpose = :p AND t.used_at IS NULL
                AND t.expires_at > :now AND u.is_active = 1',
            ['hash' => hash('sha256', $secret), 'p' => $purpose, 'now' => date('Y-m-d H:i:s')]
        );
        return $id === null ? null : (int) $id;
    }

    /**
     * Use up a secret. Returns the user id, or null if it is invalid, expired
     * or already used. Must be called inside a transaction when combined with
     * other changes (e.g. the new password).
     */
    public static function consume(string $secret, string $purpose): ?int
    {
        $userId = self::peek($secret, $purpose);
        if ($userId === null) {
            return null;
        }
        $used = Database::execute(
            'UPDATE user_tokens SET used_at = :now
              WHERE token_hash = :hash AND purpose = :p AND used_at IS NULL AND expires_at > :now2',
            ['now' => date('Y-m-d H:i:s'), 'hash' => hash('sha256', $secret), 'p' => $purpose, 'now2' => date('Y-m-d H:i:s')]
        );
        return $used === 1 ? $userId : null;
    }

    public static function looksValid(string $secret): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/', $secret);
    }

    private static function assertPurpose(string $purpose): void
    {
        if (!isset(self::TTL_MINUTES[$purpose])) {
            throw new InvalidArgumentException('Unknown token purpose.');
        }
    }
}
