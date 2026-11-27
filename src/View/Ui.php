<?php
declare(strict_types=1);

namespace SignalOps\Status\View;

use SignalOps\Status\I18n\I18n;

/**
 * Shared components. Every method returns safe Html; plain-string arguments are escaped.
 * Tones: 'ok' (green), 'warn' (amber), 'info' (blue), 'neutral' (grey). Colours only ever
 * mean state; never pick a tone for decoration.
 * CSS: public/assets/site/core/core.css (class prefix "ui-").
 */
final class Ui
{
    public function __construct(private readonly I18n $i18n)
    {
    }

    private function e(mixed $value): string
    {
        if ($value instanceof Html) {
            return (string)$value;
        }
        return htmlspecialchars(is_scalar($value) || $value instanceof \Stringable ? (string)$value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function tone(string $tone): string
    {
        return in_array($tone, ['ok', 'warn', 'info', 'neutral'], true) ? $tone : 'neutral';
    }

    public function raw(string $html): Html
    {
        return new Html($html);
    }

    /** Status label: WARNING / CRITICAL / OK. */
    public function badge(string $tone, string|Html $text): Html
    {
        return new Html('<span class="ui-badge ui-tone-' . self::tone($tone) . '">' . $this->e($text) . '</span>');
    }

    /** Small outlined NEW marker used in the nav and section titles. */
    public function newBadge(): Html
    {
        return new Html('<span class="ui-new">' . $this->e($this->i18n->t('common.badge.new')) . '</span>');
    }

    public function lockIcon(): Html
    {
        $label = $this->e($this->i18n->t('common.nav.login_required'));
        return new Html('<svg class="ui-lock" role="img" aria-label="' . $label . '" width="12" height="12" viewBox="0 0 12 12"><title>' . $label . '</title><rect x="2" y="5.5" width="8" height="5.5" rx="1" fill="none" stroke="currentColor" stroke-width="1.2"/><path d="M4 5.5V4a2 2 0 0 1 4 0v1.5" fill="none" stroke="currentColor" stroke-width="1.2"/></svg>');
    }

    public function dot(string $tone): Html
    {
        return new Html('<span class="ui-dot ui-tone-' . self::tone($tone) . '" aria-hidden="true"></span>');
    }

    /** Horizontal bar with the number beside it. $pct is clamped to 0..100 for the bar only. */
    public function meter(?float $pct, string $tone = 'ok', ?string $label = null, int $decimals = 1): Html
    {
        if ($pct === null) {
            return new Html('<span class="ui-meter ui-meter-empty">' . $this->e($this->i18n->t('common.value.none')) . '</span>');
        }
        $width = max(0, min(100, $pct));
        $text = $label ?? $this->i18n->percent($pct, $decimals);
        return new Html('<span class="ui-meter ui-tone-' . self::tone($tone) . '"><span class="ui-meter-track" aria-hidden="true"><span class="ui-meter-fill" style="width:' . round($width, 2) . '%"></span></span><span class="ui-meter-value">' . $this->e($text) . '</span></span>');
    }

    /**
     * Metric strip. Items: ['label' => str, 'value' => str|Html, 'sub' => str|Html|null, 'tone' => tone|null].
     * @param list<array<string, mixed>> $items
     */
    public function kpis(array $items, string $label = ''): Html
    {
        $out = '<section class="ui-kpis"' . ($label !== '' ? ' aria-label="' . $this->e($label) . '"' : '') . '>';
        foreach ($items as $item) {
            $tone = isset($item['tone']) ? ' ui-tone-' . self::tone((string)$item['tone']) : '';
            $out .= '<div class="ui-kpi"><div class="ui-kpi-label">' . $this->e($item['label'] ?? '') . '</div>'
                . '<div class="ui-kpi-value' . $tone . '">' . $this->e($item['value'] ?? '') . '</div>';
            if (isset($item['sub']) && $item['sub'] !== '') {
                $out .= '<div class="ui-kpi-sub">' . $this->e($item['sub']) . '</div>';
            }
            $out .= '</div>';
        }
        return new Html($out . '</section>');
    }

    /**
     * Panel (card with a header). Use panelStart()/panelEnd() around your own markup, or panel() with a body.
     * Options: id, meta (right-side text or Html), badge (Html beside the title), flush (bool: no body padding).
     */
    public function panelStart(string|Html $title, array $opts = []): Html
    {
        $id = (string)($opts['id'] ?? ('p-' . substr(sha1((string)$title), 0, 8)));
        $out = '<section class="ui-panel" aria-labelledby="' . $this->e($id) . '"><header class="ui-panel-head"><div class="ui-panel-title"><h2 id="' . $this->e($id) . '">' . $this->e($title) . '</h2>';
        if (isset($opts['badge'])) {
            $out .= $this->e($opts['badge']);
        }
        $out .= '</div>';
        if (isset($opts['meta']) && $opts['meta'] !== '') {
            $out .= '<div class="ui-panel-meta">' . $this->e($opts['meta']) . '</div>';
        }
        $out .= '</header><div class="ui-panel-body' . (!empty($opts['flush']) ? ' ui-flush' : '') . '">';
        return new Html($out);
    }

    public function panelEnd(): Html
    {
        return new Html('</div></section>');
    }

    public function panel(string|Html $title, string|Html $body, array $opts = []): Html
    {
        return new Html($this->panelStart($title, $opts) . $this->e($body) . $this->panelEnd());
    }

    /**
     * Data table. Wide tables scroll sideways inside their box on phones.
     *
     * $columns: [['key' => 'host', 'label' => 'Host', 'align' => 'right'|null, 'mono' => bool, 'class' => str]]
     * $rows: [['cells' => ['host' => str|Html, ...], 'detail' => Html|null, 'tone' => tone|null]]
     * A row with 'detail' becomes clickable and expands below (keyboard: Enter/Space on the row button).
     * Options: caption (screen-reader caption), min_width (px), empty (text when no rows), id.
     */
    public function table(array $columns, array $rows, array $opts = []): Html
    {
        $id = (string)($opts['id'] ?? ('t-' . substr(sha1(serialize(array_column($columns, 'key'))), 0, 8)));
        if ($rows === []) {
            return $this->emptyState((string)($opts['empty'] ?? $this->i18n->t('common.table.empty')));
        }
        $hasDetail = false;
        foreach ($rows as $row) {
            if (!empty($row['detail'])) {
                $hasDetail = true;
                break;
            }
        }
        $minWidth = isset($opts['min_width']) ? ' style="min-width:' . (int)$opts['min_width'] . 'px"' : '';
        $out = '<div class="ui-table-wrap"><table class="ui-table" id="' . $this->e($id) . '"' . $minWidth . '>';
        if (!empty($opts['caption'])) {
            $out .= '<caption class="ui-sr-only">' . $this->e($opts['caption']) . '</caption>';
        }
        $out .= '<thead><tr>';
        if ($hasDetail) {
            $out .= '<th class="ui-col-toggle"><span class="ui-sr-only">' . $this->e($this->i18n->t('common.table.details')) . '</span></th>';
        }
        foreach ($columns as $col) {
            $out .= '<th scope="col"' . $this->cellClass($col) . '>' . $this->e($col['label'] ?? '') . '</th>';
        }
        $out .= '</tr></thead>';
        foreach ($rows as $i => $row) {
            $tone = isset($row['tone']) ? ' ui-row-' . self::tone((string)$row['tone']) : '';
            $detailId = $id . '-d' . $i;
            $out .= '<tbody class="ui-rowgroup' . $tone . '"><tr class="ui-row' . (!empty($row['detail']) ? ' ui-row-expandable' : '') . '">';
            if ($hasDetail) {
                $out .= '<td class="ui-col-toggle">';
                if (!empty($row['detail'])) {
                    $out .= '<button type="button" class="ui-row-toggle" aria-expanded="false" aria-controls="' . $this->e($detailId) . '"><span class="ui-sr-only">' . $this->e($this->i18n->t('common.table.show_details')) . '</span><svg aria-hidden="true" width="10" height="10" viewBox="0 0 10 10"><path d="M3 2l4 3-4 3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
                }
                $out .= '</td>';
            }
            foreach ($columns as $col) {
                $key = (string)($col['key'] ?? '');
                $out .= '<td' . $this->cellClass($col) . '>' . $this->e($row['cells'][$key] ?? '') . '</td>';
            }
            $out .= '</tr>';
            if (!empty($row['detail'])) {
                $out .= '<tr class="ui-row-detail" id="' . $this->e($detailId) . '" hidden><td colspan="' . (count($columns) + 1) . '">' . $this->e($row['detail']) . '</td></tr>';
            }
            $out .= '</tbody>';
        }
        return new Html($out . '</table></div>');
    }

    private function cellClass(array $col): string
    {
        $classes = [];
        if (($col['align'] ?? '') === 'right') {
            $classes[] = 'ui-right';
        }
        if (!empty($col['mono'])) {
            $classes[] = 'ui-mono';
        }
        if (!empty($col['class'])) {
            $classes[] = (string)$col['class'];
        }
        return $classes === [] ? '' : ' class="' . $this->e(implode(' ', $classes)) . '"';
    }

    /**
     * Tabs as links (server-side state, works without JS).
     * Items: [['label' => str, 'href' => str, 'active' => bool, 'count' => int|null]]
     */
    public function tabs(array $items, string $label = ''): Html
    {
        $out = '<nav class="ui-tabs"' . ($label !== '' ? ' aria-label="' . $this->e($label) . '"' : '') . '>';
        foreach ($items as $item) {
            $active = !empty($item['active']);
            $out .= '<a class="ui-tab' . ($active ? ' is-active' : '') . '" href="' . $this->e($item['href'] ?? '#') . '"' . ($active ? ' aria-current="page"' : '') . '>' . $this->e($item['label'] ?? '');
            if (isset($item['count'])) {
                $out .= ' <span class="ui-tab-count">' . $this->e($this->i18n->number((int)$item['count'])) . '</span>';
            }
            $out .= '</a>';
        }
        return new Html($out . '</nav>');
    }

    /** Inline message box. */
    public function notice(string $tone, string|Html $title, string|Html $text = ''): Html
    {
        $role = in_array($tone, ['warn'], true) ? ' role="status"' : '';
        $out = '<div class="ui-notice ui-tone-' . self::tone($tone) . '"' . $role . '>' . $this->dot($tone) . '<div><strong>' . $this->e($title) . '</strong>';
        if ((string)$text !== '') {
            $out .= ' <span class="ui-notice-text">' . $this->e($text) . '</span>';
        }
        return new Html($out . '</div></div>');
    }

    public function emptyState(string|Html $title, string|Html $text = ''): Html
    {
        $out = '<div class="ui-empty"><div class="ui-empty-title">' . $this->e($title) . '</div>';
        if ((string)$text !== '') {
            $out .= '<div class="ui-empty-text">' . $this->e($text) . '</div>';
        }
        return new Html($out . '</div>');
    }

    /** <time> showing relative age, full local time on hover. */
    public function time(mixed $value, string $mode = 'relative', ?int $now = null): Html
    {
        $ts = I18n::timestamp($value);
        if ($ts === null) {
            return new Html('<span class="ui-muted">' . $this->e($this->i18n->t('common.value.none')) . '</span>');
        }
        $text = $mode === 'relative' ? $this->i18n->relative($ts, $now) : $this->i18n->datetime($ts, $mode);
        return new Html('<time datetime="' . gmdate('Y-m-d\TH:i:s\Z', $ts) . '" title="' . $this->e($this->i18n->datetime($ts, 'full')) . '">' . $this->e($text) . '</time>');
    }

    /** Key/value list for row details: [['label' => str, 'value' => str|Html, 'mono' => bool]]. */
    public function facts(array $items): Html
    {
        $out = '<dl class="ui-facts">';
        foreach ($items as $item) {
            $out .= '<div><dt>' . $this->e($item['label'] ?? '') . '</dt><dd' . (!empty($item['mono']) ? ' class="ui-mono"' : '') . '>' . $this->e($item['value'] ?? '') . '</dd></div>';
        }
        return new Html($out . '</dl>');
    }
}
