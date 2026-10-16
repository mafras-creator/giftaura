<?php
/**
 * GiftAura - store settings (settings table).
 * Values are loaded once per request and cached.
 * Editing settings from the admin panel is added in Phase 6.
 */
declare(strict_types=1);

final class SettingsService
{
    private static ?array $cache = null;

    /** All settings as key => value. */
    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Database::fetchAll('SELECT setting_key, setting_value FROM settings') as $row) {
                self::$cache[$row['setting_key']] = (string) $row['setting_value'];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, string $default = ''): string
    {
        $value = self::all()[$key] ?? '';
        return $value !== '' ? $value : $default;
    }

    /** Forget cached values (after an update). */
    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
