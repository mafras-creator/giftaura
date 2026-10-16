<?php
/**
 * GiftAura - store settings (settings table).
 * Values are loaded once per request and cached.
 * Administrators edit them on Admin -> Settings; each value is validated
 * according to its value_type.
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

    /** Full rows (key, value, type, label, description) for the settings form. */
    public static function rows(): array
    {
        return Database::fetchAll('SELECT * FROM settings ORDER BY setting_key');
    }

    /**
     * Validate and save submitted values. Unknown keys are ignored.
     * Returns errors (key => message); empty array means saved.
     */
    public static function update(array $input, int $adminId): array
    {
        $errors = [];
        $clean = [];
        foreach (self::rows() as $row) {
            $key = $row['setting_key'];
            $raw = $input[$key] ?? ($row['value_type'] === 'boolean' ? '0' : null);
            if ($raw === null || !is_string($raw)) {
                continue;
            }
            $value = $row['value_type'] === 'multiline' ? trim(str_replace("\r\n", "\n", $raw)) : trim($raw);

            switch ($row['value_type']) {
                case 'money':
                    if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $value)) {
                        $errors[$key] = 'Enter an amount such as 400 or 400.00.';
                        continue 2;
                    }
                    $value = CartService::fromCents(CartService::toCents($value));
                    break;
                case 'integer':
                    if (!preg_match('/^\d{1,4}$/', $value)) {
                        $errors[$key] = 'Enter a whole number.';
                        continue 2;
                    }
                    if ($key === 'max_quantity_per_item' && ((int) $value < 1 || (int) $value > 99)) {
                        $errors[$key] = 'Choose a number from 1 to 99.';
                        continue 2;
                    }
                    break;
                case 'boolean':
                    $value = $value === '1' ? '1' : '0';
                    break;
                case 'email':
                    if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$key] = 'Enter a valid email address or leave it empty.';
                        continue 2;
                    }
                    break;
                default:
                    if (mb_strlen($value) > ($row['value_type'] === 'multiline' ? 1000 : 255)) {
                        $errors[$key] = 'This text is too long.';
                        continue 2;
                    }
            }
            if ($key === 'store_name' && $value === '') {
                $errors[$key] = 'The store name cannot be empty.';
                continue;
            }
            $clean[$key] = $value;
        }

        if (($clean['cod_enabled'] ?? '1') === '0' && ($clean['bank_transfer_enabled'] ?? '1') === '0') {
            $errors['cod_enabled'] = 'At least one payment method must stay enabled, otherwise customers cannot order.';
        }
        if ($errors !== []) {
            return $errors;
        }

        $changed = [];
        Database::transaction(static function () use ($clean, &$changed): void {
            $current = self::all();
            foreach ($clean as $key => $value) {
                if (($current[$key] ?? null) !== $value) {
                    Database::execute('UPDATE settings SET setting_value = :v WHERE setting_key = :k', ['v' => $value, 'k' => $key]);
                    $changed[] = $key;
                }
            }
        });
        self::clearCache();
        if ($changed !== []) {
            // Log which settings changed, but never their values (bank details are private).
            ActivityLogService::log($adminId, 'settings.update', 'settings', null, 'Changed: ' . implode(', ', $changed));
        }
        return [];
    }
}
