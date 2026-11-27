<?php
declare(strict_types=1);

namespace SignalOps\Status\Home;

use SignalOps\Status\App;
use SignalOps\Status\View\Html;
use SignalOps\Status\View\Ui;

/** Small HTML builders shared by the MONITOR templates. Every value is escaped here. */
final class MonitorView
{
    private static function e(mixed $value): string
    {
        return htmlspecialchars(is_scalar($value) ? (string)$value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Machine details: CPU model, cores, memory, ping, traffic, SLA, uptime, disks, services, probe error. */
    public static function serverDetail(App $app, Ui $ui, array $m): Html
    {
        $none = $app->t('common.value.none');
        $disks = '';
        foreach ($m['disks'] as $disk) {
            $disks .= '<span class="home-disk"><span class="ui-mono">' . self::e($disk['path']) . '</span> '
                . '<strong class="ui-tone-' . self::e($disk['tone']) . '">' . self::e($disk['pct'] === null ? $none : compact_percent($disk['pct'])) . '</strong>'
                . ($disk['free'] !== null ? ' <span class="ui-muted">' . self::e($app->t('home.servers.free', ['size' => $disk['free']])) . '</span>' : '')
                . '</span>';
        }
        $services = '';
        foreach ($m['services'] as $service) {
            $services .= '<span class="home-service ui-tone-' . self::e($service['tone']) . '" title="' . self::e($service['state']) . '">'
                . self::e($service['name']) . ($service['state'] !== 'active' ? ': ' . self::e($service['state']) : '') . '</span>';
        }
        $facts = [];
        if ($m['role'] !== '') {
            $facts[] = ['label' => $app->t('home.servers.role'), 'value' => $m['role']];
        }
        $facts = [
            ...$facts,
            ['label' => $app->t('home.servers.cpu_model'), 'value' => $m['cpu_model'] ?? $none, 'mono' => true],
            ['label' => $app->t('home.servers.cores'), 'value' => $m['cores'] ?? $none, 'mono' => true],
            ['label' => $app->t('home.servers.memory'), 'value' => ($m['mem_used'] ?? $none) . ' / ' . ($m['mem_total'] ?? $none), 'mono' => true],
            ['label' => $app->t('home.servers.ping'), 'value' => $app->t('home.servers.ping_state.' . $m['ping_state']) . ' / ' . $m['ping_text'], 'mono' => true],
            ['label' => $app->t('home.servers.traffic'), 'value' => $app->t('home.servers.traffic_value', ['today' => $m['traffic_today'], 'month' => $m['traffic_month'] ?? $none, 'avg' => $m['traffic_avg'] ?? $none]), 'mono' => true],
            ['label' => $app->t('home.servers.month_sla'), 'value' => $m['sla_text'], 'mono' => true],
        ];
        if ($m['uptime'] !== null) {
            $facts[] = ['label' => $app->t('home.servers.uptime'), 'value' => $m['uptime'], 'mono' => true];
        }
        $facts[] = ['label' => $app->t('home.servers.disks'), 'value' => $ui->raw($disks === '' ? self::e($none) : $disks)];
        return $ui->raw('<div class="home-server-detail">' . $ui->facts($facts)
            . '<div class="home-services"><div class="home-services-label">' . self::e($app->t('home.servers.services')) . '</div>'
            . ($services === '' ? '<span class="ui-muted">' . self::e($app->t('home.servers.no_services')) . '</span>' : $services) . '</div>'
            . ($m['error'] !== null ? $ui->notice('warn', $app->t('home.servers.probe_error'), $m['error']) : '')
            . '</div>');
    }

    /** Ui::meter() wrapped with the live key the refresh script updates. */
    public static function meter(Ui $ui, array $live, string $key, ?float $pct, string $tone): Html
    {
        return $ui->raw('<span class="home-meter" data-live-meter="' . self::e($key) . '">' . $ui->meter($pct, $tone, $live[$key]['t'] ?? null) . '</span>');
    }

    /** <span data-live> with optional tone. */
    public static function live(string $key, string $text, ?string $tone = null, string $class = ''): Html
    {
        $classes = trim($class . ($tone !== null && $tone !== 'none' ? ' ui-tone-' . $tone : ''));
        return new Html('<span' . ($classes !== '' ? ' class="' . self::e($classes) . '"' : '') . ' data-live="' . self::e($key) . '"'
            . ($tone !== null ? ' data-live-tone="' . self::e($key) . '"' : '') . '>' . self::e($text) . '</span>');
    }
}
