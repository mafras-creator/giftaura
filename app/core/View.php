<?php
/**
 * GiftAura - template rendering.
 *
 * View::render('shop/login', ['title' => 'Log in', ...]);
 *   -> renders app/views/shop/login.php inside app/views/layouts/shop.php
 *
 * Templates whose path starts with "admin/" use the admin layout.
 * Pass 'layout' => false to render without a layout.
 * Inside templates, always print values with e().
 */
declare(strict_types=1);

final class View
{
    public static function render(string $template, array $data = []): void
    {
        $layout = $data['layout'] ?? (str_starts_with($template, 'admin/') ? 'admin' : 'shop');
        $content = self::capture($template, $data);
        if ($layout === false) {
            echo $content;
            return;
        }
        echo self::capture('layouts/' . $layout, $data + ['content' => $content]);
    }

    /** Render a partial and return it as a string (e.g. a product card). */
    public static function partial(string $template, array $data = []): string
    {
        return self::capture('partials/' . $template, $data);
    }

    private static function capture(string $template, array $data): string
    {
        if (!preg_match('#^[a-z0-9_\-/]+$#', $template)) {
            throw new InvalidArgumentException('Invalid template name.');
        }
        $file = APP_DIR . '/views/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('Template not found: ' . $template);
        }
        ob_start();
        try {
            (static function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })($file, $data);
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
