<?php
declare(strict_types=1);

namespace SignalOps\Status\View;

/**
 * Emits <link>/<script type="module"> tags for the site's asset entries. No build step: each entry
 * is a hand-written pair of plain files, public/assets/site/<entry>/<entry>.css and <entry>.js,
 * cache-busted with ?v=<filemtime> so the web server can cache /assets/ for a long time.
 *
 * Entries: 'core' (tokens, layout, components, theme switcher, clock, expandable tables) on every
 * page, plus one per area ('home').
 */
final class Assets
{
    public const ENTRIES = ['core', 'home'];

    public function __construct(private readonly string $assetDir, private readonly string $baseUrl)
    {
    }

    public static function forSite(string $root): self
    {
        return new self(rtrim($root, '/') . '/public/assets/site', '/assets/site/');
    }

    /** Public URL of a file of an entry, with ?v=<mtime> when the file exists. */
    public function url(string $entry, string $file): string
    {
        $file = ltrim($file, '/');
        $url = $this->baseUrl . $entry . '/' . $file;
        $mtime = @filemtime($this->assetDir . '/' . $entry . '/' . $file);
        return $mtime !== false ? $url . '?v=' . $mtime : $url;
    }

    /**
     * Tags for the given entries: stylesheets first, then module scripts. Unknown entries are
     * skipped; an entry with neither file leaves an HTML comment.
     * @param list<string> $entries
     */
    public function tags(array $entries): string
    {
        $css = [];
        $scripts = [];
        foreach (array_unique($entries) as $entry) {
            if (!in_array($entry, self::ENTRIES, true)) {
                continue;
            }
            $dir = $this->assetDir . '/' . $entry;
            $hasCss = is_file($dir . '/' . $entry . '.css');
            $hasJs = is_file($dir . '/' . $entry . '.js');
            if ($hasCss) {
                $css[] = '<link rel="stylesheet" href="' . $this->attr($this->url($entry, $entry . '.css')) . '">';
            }
            if ($hasJs) {
                $scripts[] = '<script type="module" src="' . $this->attr($this->url($entry, $entry . '.js')) . '"></script>';
            }
            if (!$hasCss && !$hasJs) {
                $scripts[] = '<!-- asset entry "' . $entry . '" is missing: public/assets/site/' . $entry . '/ -->';
            }
        }
        return implode("\n", array_merge($css, $scripts));
    }

    private function attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
