<?php
/**
 * GiftAura - content of the About page (Admin -> About page).
 *
 * Stored as JSON in storage/content/about.json (outside the web root, not
 * reachable from the browser), so no database change is needed. Until the
 * administrator saves the form, the draft texts below are shown; they
 * should be checked and adjusted by the store owner.
 */
declare(strict_types=1);

final class AboutService
{
    /** field => [label, max length, default text] */
    public const FIELDS = [
        'headline'           => ['Headline', 80, 'Thoughtful gifts,'],
        'headline_highlight' => ['Headline, second line (yellow)', 80, 'from the heart of Kurunegala'],
        'intro'              => ['Introduction', 400, 'GiftAura brings flower bouquets, beauty creams and carefully chosen gifts to your door. Order online in a few minutes and pay on delivery or by bank transfer.'],
        'founded_year'       => ['Year the shop started (optional)', 4, ''],
        'story_title'        => ['Story heading', 120, 'A gift shop built on care'],
        'story'              => ['Our story (leave an empty line between paragraphs)', 3000, "GiftAura was started in Bandarakoswatta, Kurunegala, by Asjadh Mohamed Mafras with a simple idea: choosing a gift should feel as good as receiving one.\n\nToday GiftAura is also online, so customers can browse our bouquets, beauty creams and gift items from home, place an order in a few clicks and follow it until it arrives.\n\nEvery order is checked and packed with care before it leaves us, because behind every gift is someone who wants to make another person smile."],
        'founder_name'       => ['Founder name', 100, 'Asjadh Mohamed Mafras'],
        'founder_role'       => ['Founder role', 100, 'Founder'],
        'founder_quote'      => ['Founder quote', 300, 'A gift says what words sometimes cannot. Our job is to help you say it beautifully.'],
        'mission'            => ['Our mission', 600, 'To make thoughtful gifting simple for everyone: beautiful products, clear prices in rupees, and an order you can follow from our shop to your door.'],
        'vision'             => ['Our vision', 600, 'To be the gift shop that people in Kurunegala and across Sri Lanka think of first for every birthday, celebration and thank-you.'],
        'location'           => ['Location', 200, 'Bandarakoswatta, Kurunegala, Sri Lanka'],
        'map_query'          => ['Map search (what Google Maps should look for)', 200, 'Bandarakoswatta, Kurunegala, Sri Lanka'],
    ];

    private static ?array $cache = null;

    private static function file(): string
    {
        return APP_ROOT . '/storage/content/about.json';
    }

    /** All texts (saved values over the defaults) plus photo and show_map. */
    public static function get(): array
    {
        if (self::$cache === null) {
            $saved = [];
            if (is_file(self::file())) {
                $decoded = json_decode((string) file_get_contents(self::file()), true);
                $saved = is_array($decoded) ? $decoded : [];
            }
            $data = [];
            foreach (self::FIELDS as $key => [, , $default]) {
                $data[$key] = isset($saved[$key]) && is_string($saved[$key]) ? $saved[$key] : $default;
            }
            $data['founder_photo'] = isset($saved['founder_photo']) && is_string($saved['founder_photo']) ? $saved['founder_photo'] : null;
            $data['show_map'] = !isset($saved['show_map']) || $saved['show_map'] === true;
            $data['is_saved'] = $saved !== [];
            self::$cache = $data;
        }
        return self::$cache;
    }

    public static function founderPhotoUrl(): ?string
    {
        return ImageUploader::url(self::get()['founder_photo'], 'people');
    }

    /** "Asjadh Mohamed Mafras" -> "AM" (shown when there is no photo). */
    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = array_map(static fn(string $p): string => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($parts, 0, 2));
        return implode('', $letters);
    }

    /** Story text split into paragraphs. */
    public static function paragraphs(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split("/\n\s*\n/", str_replace("\r\n", "\n", $text)) ?: [])));
    }

    /**
     * Save the form. $photo is $_FILES['founder_photo'] (optional).
     * Returns ['errors' => [...], 'old' => [...]] or [] on success.
     */
    public static function save(array $input, ?array $photo, bool $removePhoto, int $adminId): array
    {
        $current = self::get();
        $clean = [];
        $errors = [];
        foreach (self::FIELDS as $key => [$label, $max]) {
            $value = trim(str_replace("\r\n", "\n", (string) ($input[$key] ?? '')));
            if (mb_strlen($value) > $max) {
                $errors[$key] = $label . ' can be at most ' . $max . ' characters.';
            }
            $clean[$key] = $value;
        }
        foreach (['headline', 'founder_name', 'story'] as $required) {
            if ($clean[$required] === '') {
                $errors[$required] = 'Please fill in this field.';
            }
        }
        if ($clean['founded_year'] !== '' && (!ctype_digit($clean['founded_year']) || (int) $clean['founded_year'] < 1950 || (int) $clean['founded_year'] > (int) date('Y'))) {
            $errors['founded_year'] = 'Enter a year such as 2023, or leave it empty.';
        }
        $clean['show_map'] = ($input['show_map'] ?? '') === '1';
        if ($errors !== []) {
            return ['errors' => $errors, 'old' => $clean];
        }

        $clean['founder_photo'] = $current['founder_photo'];
        $hasPhoto = $photo !== null && ($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($hasPhoto) {
            $upload = ImageUploader::store($photo, false, 'people');
            if (isset($upload['error'])) {
                return ['errors' => ['founder_photo' => $upload['error']], 'old' => $clean];
            }
            if ($current['founder_photo'] !== null) {
                ImageUploader::delete($current['founder_photo'], 'people');
            }
            $clean['founder_photo'] = $upload['file'];
        } elseif ($removePhoto && $current['founder_photo'] !== null) {
            ImageUploader::delete($current['founder_photo'], 'people');
            $clean['founder_photo'] = null;
        }

        $dir = dirname(self::file());
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['errors' => ['headline' => 'The folder storage/content could not be created.'], 'old' => $clean];
        }
        $tmp = self::file() . '.tmp';
        if (@file_put_contents($tmp, json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false || !@rename($tmp, self::file())) {
            @unlink($tmp);
            return ['errors' => ['headline' => 'The About page could not be saved (is storage/content writable?).'], 'old' => $clean];
        }
        self::$cache = null;
        ActivityLogService::log($adminId, 'about.update', 'settings', null, 'About page updated' . ($hasPhoto ? ' (new founder photo)' : ''));
        return [];
    }
}
