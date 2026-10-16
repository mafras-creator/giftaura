<?php
/**
 * GiftAura - simple file logger: storage/logs/app-YYYY-MM-DD.log
 *
 * Context values whose key looks sensitive (password, token, csrf,
 * secret, account number) are replaced with [removed] so they are
 * never written to disk.
 */
declare(strict_types=1);

final class Logger
{
    private const SENSITIVE = '/pass|token|csrf|secret|account_number|card/i';

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        foreach ($context as $key => $value) {
            if (preg_match(self::SENSITIVE, (string) $key)) {
                $context[$key] = '[removed]';
            } elseif (!is_scalar($value) && $value !== null) {
                $context[$key] = json_encode($value);
            }
        }
        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            str_replace(["\r", "\n"], ' ', $message),
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''
        );
        $dir = APP_ROOT . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}
