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

    private function capture(string $template, array $data): string
    {
        $file = $this->viewPath . '/' . str_replace('.', '/', $template) . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("View not found: {$template} (looked in {$file})");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
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
