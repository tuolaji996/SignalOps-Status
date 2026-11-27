<?php
declare(strict_types=1);

namespace SignalOps\Status\View;

use SignalOps\Status\App;
use SignalOps\Status\Http\Response;

/**
 * Renders templates/<name>.php inside the shared layout.
 *
 * Inside a template these variables exist: $v (this View), $ui (components), $app, plus the
 * keys of $data. Escape every value with $v->e(); Ui helpers already return safe Html.
 */
final class View
{
    public readonly Ui $ui;

    public function __construct(private readonly App $app, public readonly Assets $assetTags)
    {
        $this->ui = new Ui($app->i18n);
    }

    public function e(mixed $value): string
    {
        if ($value instanceof Html) {
            return (string)$value;
        }
        return htmlspecialchars(is_scalar($value) || $value instanceof \Stringable ? (string)$value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function t(string $key, array $params = []): string
    {
        return $this->e($this->app->i18n->t($key, $params));
    }

    /** Render a template to a string. $name is relative to templates/, without .php. */
    public function render(string $name, array $data = []): string
    {
        if (!preg_match('#^[a-z0-9_-]+(/[a-z0-9_-]+)*$#', $name)) {
            throw new \InvalidArgumentException('Bad template name: ' . $name);
        }
        $file = $this->app->root . '/templates/' . $name . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Template not found: ' . $name);
        }
        $vars = $data + ['v' => $this, 'ui' => $this->ui, 'app' => $this->app];
        ob_start();
        try {
            (static function (string $__file, array $__vars): void {
                extract($__vars, EXTR_SKIP);
                require $__file;
            })($file, $vars);
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }
        return (string)ob_get_clean();
    }

    /** Shorthand for templates: echo $v->partial('components/x', [...]). */
    public function partial(string $name, array $data = []): Html
    {
        return new Html($this->render($name, $data));
    }

    /**
     * A full page in the shared layout.
     *
     * $page keys: title (plain text, already translated), area ('home' — picks the asset entry and
     * body class), nav (active nav key), private (bool: no-store headers), subtitle, actions (Html
     * for the right side of the page header), status (Html for the top status strip),
     * hide_heading (bool), status_code (int).
     */
    public function page(string $template, array $data = [], array $page = []): Response
    {
        $content = $this->render($template, $data);
        $page += ['title' => '', 'area' => 'home', 'nav' => '', 'private' => false, 'status_code' => 200];
        $html = $this->render('layout/base', ['content' => new Html($content), 'page' => $page]);
        $response = new Response($html, (int)$page['status_code']);
        if ($page['private']) {
            $response->private();
        }
        return $response;
    }

    /** Asset tags for the layout: the shared core plus the page's area entry. */
    public function assets(string $area): Html
    {
        $entries = ['core'];
        if ($area !== 'core' && in_array($area, Assets::ENTRIES, true)) {
            $entries[] = $area;
        }
        return new Html($this->assetTags->tags($entries));
    }
}
