<?php
/**
 * GiftAura - one-time messages shown on the next page
 * (e.g. "Your profile was updated.").
 * Types: success, error, info, warning.
 */
declare(strict_types=1);

final class Flash
{
    public static function add(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** Return all messages and clear them. */
    public static function pull(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return is_array($messages) ? $messages : [];
    }
}
