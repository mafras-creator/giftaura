<?php
/**
 * GiftAura - homepage banners (slides), managed in Admin -> Banners.
 *
 * - The picture is uploaded through ImageUploader (checked and re-encoded,
 *   random file name) into public/uploads/banners.
 * - A banner is shown when it is active and today is between its optional
 *   start and end dates. At most MAX_SHOWN banners rotate on the homepage.
 * - The button link may only be a page of this shop (e.g.
 *   "products.php?category=flowers") or an http(s) address, so a link like
 *   "javascript:..." can never be stored.
 */
declare(strict_types=1);

final class BannerService
{
    public const MAX_SHOWN = 6;
    public const MIN_WIDTH = 1000;

    /** Banners to show on the homepage today, in display order. */
    public static function active(): array
    {
        $today = date('Y-m-d');
        $rows = Database::fetchAll(
            'SELECT id, title, subtitle, button_text, link_url, image_file, show_text
               FROM banners
              WHERE is_active = 1
                AND (starts_on IS NULL OR starts_on <= :t1)
                AND (ends_on IS NULL OR ends_on >= :t2)
              ORDER BY sort_order, id
              LIMIT ' . self::MAX_SHOWN,
            ['t1' => $today, 't2' => $today]
        );
        // Skip a banner whose picture file is missing (e.g. not restored from a backup).
        return array_values(array_filter($rows, static fn(array $b): bool => ImageUploader::url($b['image_file'], 'banners') !== null));
    }

    /** Every banner, for the admin list. */
    public static function all(): array
    {
        return Database::fetchAll('SELECT * FROM banners ORDER BY sort_order, id');
    }

    public static function find(int $id): ?array
    {
        return Database::fetchOne('SELECT * FROM banners WHERE id = :id', ['id' => $id]);
    }

    /** 'Showing', 'Scheduled', 'Ended' or 'Hidden' for the admin list. */
    public static function state(array $banner): string
    {
        $today = date('Y-m-d');
        if ((int) $banner['is_active'] !== 1) {
            return 'Hidden';
        }
        if ($banner['starts_on'] !== null && $banner['starts_on'] > $today) {
            return 'Scheduled';
        }
        if ($banner['ends_on'] !== null && $banner['ends_on'] < $today) {
            return 'Ended';
        }
        return 'Showing';
    }

    /** Where the banner button goes: a shop URL, an http(s) URL, or null. */
    public static function href(?string $link): ?string
    {
        $link = trim((string) $link);
        if ($link === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $link)) {
            return filter_var($link, FILTER_VALIDATE_URL) ? $link : null;
        }
        // A page of this shop: no scheme, no "//", no backslashes, safe characters only.
        if (preg_match('#^[A-Za-z0-9][A-Za-z0-9_\-./?=&%+]*$#', $link) && !str_contains($link, '..') && !str_contains($link, '//')) {
            return url(ltrim($link, '/'));
        }
        return null;
    }

    /**
     * Create (id null) or update a banner. $file is $_FILES['image'] (required
     * for a new banner, optional when editing). Returns ['id' => int] or
     * ['errors' => [...], 'old' => [...]].
     */
    public static function save(?int $id, array $input, ?array $file, int $adminId): array
    {
        $old = [
            'title'       => trim($input['title'] ?? ''),
            'subtitle'    => trim($input['subtitle'] ?? ''),
            'button_text' => trim($input['button_text'] ?? ''),
            'link_url'    => trim($input['link_url'] ?? ''),
            'sort_order'  => trim($input['sort_order'] ?? '') !== '' ? trim($input['sort_order']) : '0',
            'starts_on'   => trim($input['starts_on'] ?? ''),
            'ends_on'     => trim($input['ends_on'] ?? ''),
            'show_text'   => ($input['show_text'] ?? '') === '1' ? '1' : '0',
            'is_active'   => ($input['is_active'] ?? '') === '1' ? '1' : '0',
        ];
        $existing = $id !== null ? self::find($id) : null;
        if ($id !== null && $existing === null) {
            return ['errors' => ['title' => 'Banner not found.'], 'old' => $old];
        }

        $errors = [];
        $len = static fn(string $v): int => mb_strlen($v);
        if ($old['title'] === '' || $len($old['title']) > 120) {
            $errors['title'] = 'Enter a title (up to 120 characters). It is also read out to blind visitors.';
        }
        if ($len($old['subtitle']) > 200) {
            $errors['subtitle'] = 'Use up to 200 characters.';
        }
        if ($len($old['button_text']) > 40) {
            $errors['button_text'] = 'Use up to 40 characters.';
        }
        if ($old['link_url'] !== '' && ($len($old['link_url']) > 255 || self::href($old['link_url']) === null)) {
            $errors['link_url'] = 'Use a page of this shop, e.g. products.php?category=flower-bouquets, or a full address starting with https://';
        }
        if ($old['button_text'] !== '' && $old['link_url'] === '') {
            $errors['link_url'] = 'A button needs a link.';
        }
        if (!preg_match('/^-?\d{1,4}$/', $old['sort_order'])) {
            $errors['sort_order'] = 'Enter a whole number, e.g. 1.';
        }
        foreach (['starts_on', 'ends_on'] as $f) {
            if ($old[$f] !== '' && !self::isDate($old[$f])) {
                $errors[$f] = 'Choose a valid date.';
            }
        }
        if (!isset($errors['starts_on'], $errors['ends_on']) && $old['starts_on'] !== '' && $old['ends_on'] !== ''
            && $old['ends_on'] < $old['starts_on']) {
            $errors['ends_on'] = 'The end date must be on or after the start date.';
        }

        $hasFile = $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($existing === null && !$hasFile) {
            $errors['image'] = 'Choose the banner picture (JPG, PNG or WebP, at least ' . self::MIN_WIDTH . ' px wide).';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'old' => $old];
        }

        $newFile = null;
        if ($hasFile) {
            $upload = ImageUploader::store($file, false, 'banners', self::MIN_WIDTH);
            if (isset($upload['error'])) {
                return ['errors' => ['image' => $upload['error']], 'old' => $old];
            }
            $newFile = $upload['file'];
        }

        $data = [
            'title'       => $old['title'],
            'subtitle'    => $old['subtitle'] !== '' ? $old['subtitle'] : null,
            'button_text' => $old['button_text'] !== '' ? $old['button_text'] : null,
            'link_url'    => $old['link_url'] !== '' ? $old['link_url'] : null,
            'show_text'   => (int) $old['show_text'],
            'sort_order'  => (int) $old['sort_order'],
            'is_active'   => (int) $old['is_active'],
            'starts_on'   => $old['starts_on'] !== '' ? $old['starts_on'] : null,
            'ends_on'     => $old['ends_on'] !== '' ? $old['ends_on'] : null,
        ];
        try {
            if ($existing === null) {
                $id = Database::insert(
                    'INSERT INTO banners (title, subtitle, button_text, link_url, image_file, show_text, sort_order, is_active, starts_on, ends_on)
                     VALUES (:title, :subtitle, :button_text, :link_url, :image_file, :show_text, :sort_order, :is_active, :starts_on, :ends_on)',
                    $data + ['image_file' => $newFile]
                );
                ActivityLogService::log($adminId, 'banner.create', 'banner', $id, $data['title']);
            } else {
                Database::execute(
                    'UPDATE banners SET title = :title, subtitle = :subtitle, button_text = :button_text, link_url = :link_url,
                            image_file = :image_file, show_text = :show_text, sort_order = :sort_order, is_active = :is_active,
                            starts_on = :starts_on, ends_on = :ends_on
                      WHERE id = :id',
                    $data + ['image_file' => $newFile ?? $existing['image_file'], 'id' => $id]
                );
                if ($newFile !== null) {
                    ImageUploader::delete($existing['image_file'], 'banners');
                }
                ActivityLogService::log($adminId, 'banner.update', 'banner', $id, $data['title'] . ($newFile !== null ? ' (new picture)' : ''));
            }
        } catch (Throwable $e) {
            if ($newFile !== null) {
                ImageUploader::delete($newFile, 'banners'); // do not leave an orphan file behind
            }
            throw $e;
        }
        return ['id' => (int) $id];
    }

    public static function setActive(int $id, bool $active, int $adminId): bool
    {
        $banner = self::find($id);
        if ($banner === null) {
            return false;
        }
        Database::execute('UPDATE banners SET is_active = :a WHERE id = :id', ['a' => $active ? 1 : 0, 'id' => $id]);
        ActivityLogService::log($adminId, $active ? 'banner.show' : 'banner.hide', 'banner', $id, $banner['title']);
        return true;
    }

    public static function delete(int $id, int $adminId): bool
    {
        $banner = self::find($id);
        if ($banner === null) {
            return false;
        }
        Database::execute('DELETE FROM banners WHERE id = :id', ['id' => $id]);
        ImageUploader::delete($banner['image_file'], 'banners');
        ActivityLogService::log($adminId, 'banner.delete', 'banner', $id, $banner['title']);
        return true;
    }

    private static function isDate(string $value): bool
    {
        $d = DateTime::createFromFormat('!Y-m-d', $value);
        return $d !== false && $d->format('Y-m-d') === $value;
    }
}
