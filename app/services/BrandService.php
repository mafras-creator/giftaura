<?php
/**
 * GiftAura - the shop logo (Admin -> Settings -> Logo).
 *
 * The uploaded logo is stored in public/uploads/brand/ with a random name
 * (checked and re-encoded by ImageUploader, like product photos). Only the
 * newest file is kept. Without an upload the built-in logo.svg is used.
 * No database table is needed.
 *
 * A wide logo (more than 1.6 times wider than high) usually contains the
 * shop name already, so the name text next to it is hidden.
 */
declare(strict_types=1);

final class BrandService
{
    private const FOLDER = 'brand';

    /** File name of the uploaded logo, or null for the default logo. */
    public static function logoFile(): ?string
    {
        $files = self::files();
        if ($files === []) {
            return null;
        }
        usort($files, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
        return basename($files[0]);
    }

    public static function hasCustomLogo(): bool
    {
        return self::logoFile() !== null;
    }

    public static function logoUrl(): string
    {
        return ImageUploader::url(self::logoFile(), self::FOLDER) ?? asset('img/logo.svg');
    }

    /** MIME type for the browser-tab icon. */
    public static function logoType(): string
    {
        $file = self::logoFile();
        if ($file === null) {
            return 'image/svg+xml';
        }
        return ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][pathinfo($file, PATHINFO_EXTENSION)] ?? 'image/png';
    }

    /** True when the uploaded logo is wide, i.e. probably already shows the shop name. */
    public static function isWide(): bool
    {
        $file = self::logoFile();
        if ($file === null) {
            return false;
        }
        $size = @getimagesize(ImageUploader::directory(self::FOLDER) . '/' . $file);
        return $size !== false && $size[1] > 0 && $size[0] / $size[1] > 1.6;
    }

    /** Upload a new logo. Returns an error message or null. */
    public static function upload(array $file, int $adminId): ?string
    {
        $old = self::logoFile();
        $result = ImageUploader::store($file, false, self::FOLDER);
        if (isset($result['error'])) {
            return $result['error'];
        }
        if ($old !== null) {
            ImageUploader::delete($old, self::FOLDER);
        }
        // Make sure the new file counts as the newest even within the same second.
        @touch(ImageUploader::directory(self::FOLDER) . '/' . $result['file'], time() + 1);
        ActivityLogService::log($adminId, 'settings.logo', 'settings', null, 'New logo uploaded');
        return null;
    }

    /** Go back to the built-in logo. */
    public static function reset(int $adminId): void
    {
        foreach (self::files() as $f) {
            ImageUploader::delete(basename($f), self::FOLDER);
        }
        ActivityLogService::log($adminId, 'settings.logo', 'settings', null, 'Default logo restored');
    }

    /** Full paths of the logo files (names made by ImageUploader only). */
    private static function files(): array
    {
        $dir = ImageUploader::directory(self::FOLDER);
        $files = [];
        foreach (is_dir($dir) ? (scandir($dir) ?: []) : [] as $name) {
            if (preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $name)) {
                $files[] = $dir . '/' . $name;
            }
        }
        return $files;
    }
}
