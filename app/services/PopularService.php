<?php
/**
 * GiftAura - "Popular categories" tiles on the homepage.
 *
 * The administrator chooses up to 6 categories and a short line for each
 * (Admin -> Categories -> Popular categories). The choice is stored in
 * storage/content/popular.json, so no database change is needed. Until a
 * choice is saved, the first 6 main categories are shown.
 */
declare(strict_types=1);

final class PopularService
{
    public const MAX = 6;
    public const TAGLINE_MAX = 40;

    private static function file(): string
    {
        return APP_ROOT . '/storage/content/popular.json';
    }

    /** Saved choice: [['id' => int, 'tagline' => string], ...] (may be empty). */
    public static function saved(): array
    {
        if (!is_file(self::file())) {
            return [];
        }
        $data = json_decode((string) file_get_contents(self::file()), true);
        $items = [];
        foreach (is_array($data['items'] ?? null) ? $data['items'] : [] as $item) {
            if (is_array($item) && is_int($item['id'] ?? null)) {
                $items[] = ['id' => $item['id'], 'tagline' => is_string($item['tagline'] ?? null) ? $item['tagline'] : ''];
            }
        }
        return array_slice($items, 0, self::MAX);
    }

    /** Tiles for the homepage: name, slug, tagline, picture, count. Hidden categories are skipped. */
    public static function tiles(): array
    {
        $chosen = self::saved();
        if ($chosen === []) {
            $chosen = array_map(static fn(array $c): array => ['id' => $c['id'], 'tagline' => ''],
                array_slice(CategoryService::topLevel(), 0, self::MAX));
        }
        $tiles = [];
        foreach ($chosen as $item) {
            $category = CategoryService::findById($item['id']);
            if ($category === null || ($category['parent_id'] !== null && CategoryService::findById($category['parent_id']) === null)) {
                continue;
            }
            $count = ProductService::search(['category' => CategoryService::idsIncludingChildren($category['id']), 'page' => 1])['total'];
            $tiles[] = [
                'name'    => $category['name'],
                'slug'    => $category['slug'],
                'tagline' => $item['tagline'] !== '' ? $item['tagline'] : ($count === 1 ? '1 gift' : $count . ' gifts'),
                'picture' => CategoryService::picture($category),
            ];
        }
        return $tiles;
    }

    /** Save the admin form: arrays category_id[] and tagline[]. Returns an error message or null. */
    public static function save(array $categoryIds, array $taglines, int $adminId): ?string
    {
        $items = [];
        $seen = [];
        foreach (array_slice(array_values($categoryIds), 0, self::MAX) as $i => $rawId) {
            $id = is_string($rawId) && ctype_digit($rawId) ? (int) $rawId : 0;
            if ($id === 0 || isset($seen[$id])) {
                continue;
            }
            if (CategoryAdminService::find($id) === null) {
                return 'One of the chosen categories does not exist.';
            }
            $tagline = trim((string) ($taglines[$i] ?? ''));
            if (mb_strlen($tagline) > self::TAGLINE_MAX) {
                return 'Keep each short line to ' . self::TAGLINE_MAX . ' characters.';
            }
            $seen[$id] = true;
            $items[] = ['id' => $id, 'tagline' => $tagline];
        }
        $dir = dirname(self::file());
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return 'The folder storage/content could not be created.';
        }
        $tmp = self::file() . '.tmp';
        if (@file_put_contents($tmp, json_encode(['items' => $items], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false || !@rename($tmp, self::file())) {
            @unlink($tmp);
            return 'Could not save (is storage/content writable?).';
        }
        ActivityLogService::log($adminId, 'category.popular', 'category', null, count($items) . ' popular categories chosen');
        return null;
    }
}
