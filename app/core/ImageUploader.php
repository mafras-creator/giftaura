<?php
/**
 * GiftAura - secure product image upload.
 *
 * Checks, in order:
 *   1. PHP upload error code and real upload (is_uploaded_file)
 *   2. size (max 2 MB)
 *   3. file extension (jpg, jpeg, png, webp)
 *   4. real content type with finfo (not the browser's claim)
 *   5. getimagesize(): must be a real image, 100-5000 px per side
 * Then the image is RE-ENCODED with GD into a brand-new file. This removes
 * anything hidden inside the original (for example PHP code in metadata),
 * strips EXIF data (such as GPS location) and limits the size to 1600 px.
 * The new file gets a random 32-character name; the original name is never used.
 * The uploads folder also refuses to run scripts (public/uploads/.htaccess).
 */
declare(strict_types=1);

final class ImageUploader
{
    public const MAX_BYTES = 2 * 1024 * 1024;
    public const MAX_SIDE_IN = 5000;
    public const MIN_SIDE_IN = 100;
    public const MAX_SIDE_OUT = 1600;

    private const TYPES = [
        'image/jpeg' => ['ext' => 'jpg',  'extensions' => ['jpg', 'jpeg']],
        'image/png'  => ['ext' => 'png',  'extensions' => ['png']],
        'image/webp' => ['ext' => 'webp', 'extensions' => ['webp']],
    ];

    /** Folder where product images are stored. */
    public static function directory(): string
    {
        return APP_ROOT . '/public/uploads/products';
    }

    /**
     * Validate and store one uploaded file ($_FILES['field']).
     * Returns ['file' => 'abc...123.jpg'] or ['error' => 'message'].
     * $skipUploadCheck is only for automated tests (files not sent by a browser).
     */
    public static function store(array $file, bool $skipUploadCheck = false): array
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['error' => 'Choose an image to upload.'];
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return ['error' => 'The image is too large. The maximum size is 2 MB.'];
        }
        if ($error !== UPLOAD_ERR_OK) {
            return ['error' => 'The upload failed. Please try again.'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || (!$skipUploadCheck && !is_uploaded_file($tmp))) {
            return ['error' => 'The upload failed. Please try again.'];
        }

        $size = (int) filesize($tmp);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            return ['error' => 'The image is too large. The maximum size is 2 MB.'];
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
        if (!isset(self::TYPES[$mime]) || !in_array($extension, self::TYPES[$mime]['extensions'], true)) {
            return ['error' => 'Only JPG, PNG and WebP images are allowed.'];
        }

        $info = @getimagesize($tmp);
        if ($info === false || ($info['mime'] ?? '') !== $mime) {
            return ['error' => 'This file is not a valid image.'];
        }
        [$width, $height] = $info;
        if ($width < self::MIN_SIDE_IN || $height < self::MIN_SIDE_IN) {
            return ['error' => 'The image is too small. Use at least ' . self::MIN_SIDE_IN . ' × ' . self::MIN_SIDE_IN . ' pixels.'];
        }
        if ($width > self::MAX_SIDE_IN || $height > self::MAX_SIDE_IN) {
            return ['error' => 'The image is too large. Use at most ' . self::MAX_SIDE_IN . ' × ' . self::MAX_SIDE_IN . ' pixels.'];
        }

        if (!extension_loaded('gd')) {
            return ['error' => 'Image processing (GD) is not enabled on the server.'];
        }
        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png'  => @imagecreatefrompng($tmp),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
        };
        if ($source === false) {
            return ['error' => 'This image could not be read. Try saving it again as JPG or PNG.'];
        }

        // Resize (keeping proportions) so the longest side is at most 1600 px.
        $scale = min(1, self::MAX_SIDE_OUT / max($width, $height));
        $newW = max(1, (int) round($width * $scale));
        $newH = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($newW, $newH);
        if ($mime !== 'image/jpeg') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        }
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newW, $newH, $width, $height);

        $dir = self::directory();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['error' => 'The upload folder could not be created.'];
        }
        $name = bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime]['ext'];
        $path = $dir . '/' . $name;

        $saved = match ($mime) {
            'image/jpeg' => imagejpeg($canvas, $path, 85),
            'image/png'  => imagepng($canvas, $path, 6),
            'image/webp' => function_exists('imagewebp') ? imagewebp($canvas, $path, 85) : false,
        };
        imagedestroy($source);
        imagedestroy($canvas);
        if (!$saved) {
            @unlink($path);
            return ['error' => 'The image could not be saved.'];
        }
        return ['file' => $name];
    }

    /** Delete a stored image file (only names that match the generated pattern). */
    public static function delete(string $fileName): void
    {
        if (preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $fileName)) {
            $path = self::directory() . '/' . $fileName;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
