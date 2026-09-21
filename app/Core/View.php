<?php
declare(strict_types=1);

namespace FixListed\Core;

use RuntimeException;

/** Plain PHP templates. No template language, nothing to compile, nothing to cache. */
final class View
{
    public function __construct(private readonly string $viewPath)
    {
    }

    public function render(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = $this->capture($template, $data);
        if ($layout === null) {
            return $content;
        }
        return $this->capture($layout, $data + ['content' => $content]);
    }

    /**
     * The locals here are deliberately named __-prefixed.
     *
     * extract() with EXTR_SKIP refuses to overwrite a variable that already
     * exists in scope — so a template passed `file` or `template` as data
     * silently received *this method's* value for it instead, and rendered
     * the path of the template itself. That is a bug with no error message,
     * and it found us once already. Prefixed names cannot collide with
     * anything a template would sensibly be passed.
     */
    private function capture(string $__template, array $__data): string
    {
        $__file = $this->viewPath . '/' . str_replace('.', '/', $__template) . '.php';
        if (!is_file($__file)) {
            throw new RuntimeException("View not found: {$__template} (looked in {$__file})");
        }
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            require $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /** Escape for HTML text and attribute contexts. Templates call e() everywhere. */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Cents to "$1,234.50". All money in this app is integer cents. */
    public static function money(?int $cents, bool $withCents = true): string
    {
        if ($cents === null) {
            return '—';
        }
        return '$' . number_format($cents / 100, $withCents ? 2 : 0);
    }
}
