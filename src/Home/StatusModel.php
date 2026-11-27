<?php
declare(strict_types=1);

namespace SignalOps\Status\Home;

use SignalOps\Status\I18n\I18n;

/**
 * Turns the status snapshot (collect_status() output, see app/collectors.php and app/demo.php)
 * into what the MONITOR pages show (Overview, Servers, Latency, Logs, Events, the top status strip
 * and the /overview/live JSON). Pure: no I/O.
 *
 * Every number that the page refreshes in place is also in 'live' (key => text/tone/pct); the
 * templates print it with the same key in data-live attributes and public/assets/site/home swaps
 * the values every 60 s.
 *
 * Public page rules (same as the classic dashboard): no host addresses, error text only through
 * redact(). Tones: 'ok' green, 'warn' amber, 'info' blue, 'neutral' grey. The classic 'bad' state
 * is shown amber here (colours only mean state).
 */
final class StatusModel
{
    public const DEFAULT_THRESHOLDS = [
        'cpu_pct' => 90.0,
        'mem_pct' => 90.0,
        'disk_pct' => 80.0,
        'latency_warn_ms' => 140.0,
        'latency_fast_ms' => 5.0,
    ];

    /** @var array<string, float> */
    private array $th;

    /** @var array<string, array{t: string, tone?: string, pct?: float|null}> */
    private array $live = [];

    /** @param array<string, mixed> $thresholds config home.thresholds */
    public function __construct(private readonly I18n $i18n, array $thresholds = [])
    {
        $th = self::DEFAULT_THRESHOLDS;
        foreach ($thresholds as $key => $value) {
            if (isset($th[$key]) && is_numeric($value)) {
                $th[$key] = (float)$value;
            }
        }
        $this->th = $th;
    }

    /** @return array<string, float> */
    public function thresholds(): array
    {
        return $this->th;
    }

    /**
     * @param array{state: string, status: array<string, mixed>, created_at: int|null, age: int|null, cache_state: string} $snap
     * @return array<string, mixed>
     */
    public function build(array $snap, int $now): array
    {
        $this->live = [];
        $status = $snap['status'];
        $db = self::arr($status['database'] ?? $status['db'] ?? null);
        $botHealth = self::arr($status['endpoints']['discord'] ?? $status['endpoints']['bot'] ?? null) + ['ok' => null, 'data' => []];
        $apiHealth = self::arr($status['endpoints']['api'] ?? null) + ['ok' => null, 'data' => []];
        $bot = self::arr($botHealth['data']);
        $api = self::arr($apiHealth['data']);
        $summary = self::arr($db['summary'] ?? null);
        $machinesRaw = array_values(array_filter(self::arr($status['machines'] ?? null), 'is_array'));

        $botDisplay = endpoint_display_state($botHealth);
        $apiDisplay = endpoint_display_state($apiHealth);
        $botName = (string)($botHealth['label'] ?? '') ?: $this->t('home.endpoint.bot');
        $apiName = (string)($apiHealth['label'] ?? '') ?: $this->t('home.endpoint.api');

        // ---- machines ----------------------------------------------------------------------
        $machines = [];
        $online = 0;
        $traffic = ['today' => 0.0, 'month' => 0.0, 'avg' => 0.0, 'seen' => false];
        $slaValues = [];
        $byKey = [];
        foreach ($machinesRaw as $raw) {
            $m = $this->machine($raw, $now);
            $machines[] = $m;
            $byKey[$m['key']] = $raw;
            if ($m['probe_ok'] === true) {
                $online++;
            }
            foreach (['today' => 'today_bytes', 'month' => 'month_bytes', 'avg' => 'avg_daily_bytes'] as $k => $field) {
                if (is_numeric($raw['probe']['traffic'][$field] ?? null)) {
                    $traffic[$k] += (float)$raw['probe']['traffic'][$field];
                    $traffic['seen'] = true;
                }
            }
            if (is_numeric($raw['sla']['month_pct'] ?? null)) {
                $slaValues[] = (float)$raw['sla']['month_pct'];
            }
        }

        // ---- bot error journal (machine 'discord' like app/views/dashboard.php, else the first
        // machine whose probe reads a journal) -------------------------------------------------
        $journalMachine = isset($byKey['discord']) ? 'discord' : null;
        if ($journalMachine === null) {
            foreach ($byKey as $key => $raw) {
                if (($raw['probe']['journal']['ok'] ?? null) !== null) {
                    $journalMachine = (string)$key;
                    break;
                }
            }
        }
        $discordProbe = self::arr($journalMachine !== null ? ($byKey[$journalMachine]['probe'] ?? null) : null);
        $journal = self::arr($discordProbe['journal'] ?? null);
        $journalServices = self::arr($discordProbe['services'] ?? null);
        $recovered = journal_recovered($discordProbe, $now);
        $journalLevel = $recovered ? 'ok' : journal_status_tone($journal);
        $journalLabel = $recovered ? 'recovered' : match (journal_status_label($journal)) {
            'Errors detected' => 'errors',
            'Warnings present' => 'warnings',
            'Quiet' => 'quiet',
            default => 'not_available',
        };
        $journalEntries = [];
        foreach (array_reverse(array_slice(array_values(array_filter(self::arr($journal['latest'] ?? null), 'is_array')), -8)) as $entry) {
            $isError = (int)($entry['priority'] ?? 4) <= 3;
            $journalEntries[] = [
                'time' => self::ts($entry['time'] ?? null),
                'kind' => $recovered ? 'recovered' : ($isError ? 'error' : 'warning'),
                'message' => redact($entry['message'] ?? ''),
            ];
        }

        // ---- latency -------------------------------------------------------------------------
        $latency = $this->latency(self::arr($status['latency_map'] ?? null));

        // ---- overall state -------------------------------------------------------------------
        $level = 'ok';
        $reasons = [];
        $raise = static function (string $l) use (&$level): void {
            if ($l === 'bad' || ($l === 'warn' && $level === 'ok')) {
                $level = $l;
            }
        };
        foreach ([status_tone($db['ok'] ?? null), $botDisplay['tone'], $apiDisplay['tone'], $journalLevel] as $tone) {
            $raise($tone);
        }
        if (status_tone($db['ok'] ?? null) !== 'ok') {
            $reasons[] = $this->t('home.reason.archive', ['state' => $db === [] || !isset($db['ok']) ? $this->t('home.state.not_configured') : $this->t('home.state.unavailable')]);
        }
        foreach ([['d' => $botDisplay, 'label' => $botName], ['d' => $apiDisplay, 'label' => $apiName]] as $endpoint) {
            if ($endpoint['d']['tone'] !== 'ok') {
                $reasons[] = $endpoint['label'] . ': ' . $this->endpointLabel($endpoint['d']['label']);
            }
        }
        if ($journalLevel !== 'ok') {
            $reasons[] = $this->t('home.reason.journal', ['state' => $this->t('home.journal.state.' . $journalLabel)]);
        }
        foreach ($machines as $m) {
            if ($m['ping_ok'] !== true) {
                $reasons[] = $this->t($m['ping_ok'] === false ? 'home.reason.ping_loss' : 'home.reason.ping_unmeasured', ['server' => $m['label']]);
            }
            if ($m['probe_ok'] !== true) {
                $reasons[] = $this->t($m['probe_ok'] === false ? 'home.reason.probe_down' : 'home.reason.probe_limited', ['server' => $m['label']]);
            }
            if ($m['ping_ok'] === false || ($m['probe_ok'] === false && $m['ping_ok'] !== true)) {
                $raise('bad');
            } elseif ($m['probe_ok'] !== true || $m['ping_ok'] !== true) {
                $raise('warn');
            }
        }
        if ($snap['state'] !== 'ok') {
            $level = 'warn';
            $reasons = [$this->t('home.reason.no_snapshot')];
        } elseif ($snap['cache_state'] === 'expired') {
            $raise('warn');
            array_unshift($reasons, $this->t('home.reason.snapshot_old', ['age' => $this->i18n->duration((int)$snap['age'])]));
        }
        $overall = [
            'level' => $level,
            'tone' => $level === 'ok' ? 'ok' : 'warn',
            'label' => $this->t('home.overall.' . $level),
            'reasons' => array_values(array_unique($reasons)),
        ];

        // ---- strip ---------------------------------------------------------------------------
        $cacheTone = match ($snap['cache_state']) {
            'fresh' => 'ok',
            'stale' => 'info',
            default => 'warn',
        };
        $this->put('strip.overall', $overall['label'], $overall['tone']);
        $this->put('strip.snapshot', $snap['created_at'] !== null ? $this->i18n->datetime($snap['created_at'], 'time') : $this->none());
        $this->put('strip.snapshot_age', $snap['created_at'] !== null ? $this->i18n->relative($snap['created_at'], $now) : '');
        $this->put('strip.cache', $this->t('home.cache.' . $snap['cache_state']), $cacheTone);
        $this->put('strip.probes', count($machines) > 0 ? $online . '/' . count($machines) : $this->none(), $online === count($machines) && $machines !== [] ? 'ok' : 'warn');
        $this->put('strip.reasons', $overall['reasons'] === [] ? $this->t('home.overall.no_reasons') : implode(' · ', array_slice($overall['reasons'], 0, 6)));

        // ---- KPIs ----------------------------------------------------------------------------
        $publicSla = $slaValues ? array_sum($slaValues) / count($slaValues) : null;
        $okText = fn (array $d): string => $this->endpointLabel($d['label']);
        $dbOk = $db['ok'] ?? null;
        $kpis = [
            $this->kpi('archive', $this->count($summary['event_count'] ?? null), $dbOk === true ? null : 'warn',
                $this->t('home.kpi.archive_sub', ['age' => $this->rel($summary['latest_event_at'] ?? null, $now)]) . ' · ' . $this->t($dbOk === true ? 'home.state.ok' : ($dbOk === false ? 'home.state.error' : 'home.state.not_configured'))),
            $this->kpi('dedupe', $this->count($summary['duplicate_seen_total'] ?? $bot['seen_hashes'] ?? null), null,
                $this->t('home.kpi.dedupe_sub', ['age' => $this->rel($summary['latest_insert_at'] ?? $summary['latest_event_at'] ?? null, $now)])),
            $this->kpi('discord', $this->count($bot['seen_hashes'] ?? $bot['publish_count'] ?? null), $botDisplay['tone'] === 'ok' ? null : 'warn',
                $this->t('home.kpi.discord_sub', ['age' => $this->rel($bot['last_poll_ok_at'] ?? null, $now)]) . ' · ' . $okText($botDisplay)),
            $this->kpi('api', $this->count($api['capture_count'] ?? $api['change_count'] ?? null), $apiDisplay['tone'] === 'ok' ? null : 'warn',
                $this->t('home.kpi.api_sub', ['age' => $this->rel($api['last_capture_at'] ?? null, $now)]) . ' · ' . $okText($apiDisplay)),
            $this->kpi('sla', $publicSla === null ? $this->none() : sla_percent($publicSla), $publicSla === null ? null : ($publicSla >= 99.0 ? 'ok' : 'warn'),
                $this->t('home.kpi.sla_sub')),
            $this->kpi('median', $latency['median'] === null ? $this->none() : format_latency_ms($latency['median']), null,
                $this->t('home.kpi.median_sub', ['n' => count($latency['nodes'])])),
            $this->kpi('journal', $this->t('home.journal.state.' . $journalLabel), $journalLevel === 'ok' ? 'ok' : 'warn',
                $this->t('home.kpi.journal_sub', ['w' => (int)($journal['warning_count'] ?? 0), 'e' => (int)($journal['error_count'] ?? 0)])),
            $this->kpi('traffic', $traffic['seen'] ? format_bytes($traffic['today']) : $this->none(), null,
                $this->t('home.kpi.traffic_sub', ['month' => $traffic['seen'] ? format_bytes($traffic['month']) : $this->none()])),
        ];

        // ---- right column --------------------------------------------------------------------
        $endpoints = [
            'bot' => [
                'name' => $botName,
                'tone' => $botDisplay['tone'] === 'ok' ? 'ok' : 'warn',
                'state' => $this->put('ep.bot.state', $okText($botDisplay), $botDisplay['tone'] === 'ok' ? 'ok' : 'warn'),
                'activity_label' => $this->t('home.endpoint.last_poll'),
                'activity' => $this->put('ep.bot.activity', $this->rel($bot['last_poll_ok_at'] ?? null, $now)),
                'count_label' => $this->t('home.endpoint.published'),
                'count' => $this->put('ep.bot.count', $this->count($bot['publish_count'] ?? null)),
                'http' => $this->put('ep.bot.http', isset($botHealth['http_code']) && is_scalar($botHealth['http_code']) ? (string)$botHealth['http_code'] : $this->none()),
            ],
            'api' => [
                'name' => $apiName,
                'tone' => $apiDisplay['tone'] === 'ok' ? 'ok' : 'warn',
                'state' => $this->put('ep.api.state', $okText($apiDisplay), $apiDisplay['tone'] === 'ok' ? 'ok' : 'warn'),
                'activity_label' => $this->t('home.endpoint.last_capture'),
                'activity' => $this->put('ep.api.activity', $this->rel($api['last_capture_at'] ?? null, $now)),
                'count_label' => $this->t('home.endpoint.changes'),
                'count' => $this->put('ep.api.count', $this->count($api['change_count'] ?? null)),
                'http' => $this->put('ep.api.http', isset($apiHealth['http_code']) && is_scalar($apiHealth['http_code']) ? (string)$apiHealth['http_code'] : $this->none()),
            ],
        ];
        $journalView = [
            'label' => $this->put('journal.state', $this->t('home.journal.state.' . $journalLabel), $journalLevel === 'ok' ? 'ok' : 'warn'),
            'tone' => $journalLevel === 'ok' ? 'ok' : 'warn',
            'recovered' => $recovered,
            'warnings' => $this->put('journal.warnings', (string)(int)($journal['warning_count'] ?? 0)),
            'errors' => $this->put('journal.errors', (string)(int)($journal['error_count'] ?? 0)),
            'latest' => $this->put('journal.latest', $this->rel($journal['latest_at'] ?? null, $now)),
            'entries' => $journalEntries,
            'units' => array_map('strval', array_keys($journalServices)),
        ];
        $kinds = [];
        $kindMax = 0;
        foreach (array_slice(array_values(array_filter(self::arr($db['kinds'] ?? null), 'is_array')), 0, 5) as $row) {
            $count = (int)($row['count'] ?? 0);
            $kindMax = max($kindMax, $count);
            $kinds[] = ['kind' => (string)($row['kind'] ?? '-'), 'count' => $count];
        }
        foreach ($kinds as $i => $row) {
            $kinds[$i]['pct'] = $kindMax > 0 ? 100 * $row['count'] / $kindMax : 0;
            $this->put('archive.kind.' . $i, number_format($row['count']), null, $kinds[$i]['pct']);
        }
        $archive = [
            'kinds' => $kinds,
            'symbols' => $this->put('archive.symbols', $this->count($summary['symbol_count'] ?? $db['symbols']['symbol_count'] ?? null)),
        ];
        $trafficView = [
            'today' => $this->put('traffic.today', $traffic['seen'] ? format_bytes($traffic['today']) : $this->none()),
            'month' => $this->put('traffic.month', $traffic['seen'] ? format_bytes($traffic['month']) : $this->none()),
            'avg' => $this->put('traffic.avg', $traffic['seen'] ? format_bytes($traffic['avg']) : $this->none()),
        ];

        // ---- events + runtime ----------------------------------------------------------------
        $events = [];
        foreach (array_filter(self::arr($db['recent'] ?? null), 'is_array') as $row) {
            $seen = $row['seen_at'] ?? $row['first_seen_at_utc'] ?? null;
            $events[] = [
                'time' => self::ts($seen),
                'time_text' => local_time_text($seen),
                'kind' => (string)($row['kind'] ?? '-'),
                'symbol' => ((string)($row['symbol'] ?? $row['underlying'] ?? '')) ?: '-',
                'text' => redact($row['text'] ?? ''),
            ];
        }
        $notes = [];
        if (!empty($bot['last_poll_error'])) {
            $notes[] = $botName . ': ' . redact($bot['last_poll_error']);
        }
        if (!empty($api['last_error'])) {
            $notes[] = $apiName . ': ' . redact($api['last_error']);
        }
        $runtime = [
            'poll_count' => $this->put('rt.poll_count', is_scalar($bot['poll_count'] ?? null) ? (string)$bot['poll_count'] : $this->none()),
            'publish_count' => $this->put('rt.publish_count', is_scalar($bot['publish_count'] ?? null) ? (string)$bot['publish_count'] : $this->none()),
            'last_event' => $this->put('rt.last_event', $this->rel($bot['last_event_at'] ?? null, $now)),
            'bot_capture' => $this->put('rt.bot_capture', $this->rel($bot['last_api_capture_at'] ?? null, $now)),
            'change_count' => $this->put('rt.change_count', is_scalar($api['change_count'] ?? null) ? (string)$api['change_count'] : $this->none()),
            'http' => $this->put('rt.http', isset($apiHealth['http_code']) && is_scalar($apiHealth['http_code']) ? (string)$apiHealth['http_code'] : $this->none()),
            'api_capture' => $this->put('rt.api_capture', $this->rel($api['last_capture_at'] ?? null, $now)),
            'bot_state' => $okText($botDisplay),
            'api_state' => $okText($apiDisplay),
            'notes' => $notes,
            'db_error' => !empty($db['error']) ? redact($db['error']) : null,
        ];

        return [
            'snapshot' => ['state' => $snap['state'], 'created_at' => $snap['created_at'], 'age' => $snap['age'], 'cache_state' => $snap['cache_state'], 'cache_tone' => $cacheTone, 'demo' => (bool)($snap['demo'] ?? false)],
            'overall' => $overall,
            'probes' => ['online' => $online, 'total' => count($machines)],
            'kpis' => $kpis,
            'machines' => $machines,
            'latency' => $latency,
            'endpoints' => $endpoints,
            'journal' => $journalView,
            'archive' => $archive,
            'traffic' => $trafficView,
            'events' => $events,
            'runtime' => $runtime,
            'thresholds' => $this->th,
            'live' => $this->live,
        ];
    }

    /** @param array<string, mixed> $raw */
    private function machine(array $raw, int $now): array
    {
        $key = normalize_machine_key($raw);
        $probe = self::arr($raw['probe'] ?? null);
        $cpuInfo = self::arr($probe['cpu'] ?? null);
        $memory = self::arr($probe['memory'] ?? null);
        $trafficRaw = self::arr($probe['traffic'] ?? null);
        $sla = self::arr($raw['sla'] ?? null);
        $probeOk = $probe['ok'] ?? null;
        $pingOk = $sla['last_ok'] ?? null;
        $state = $probeOk === true ? 'online' : ($probeOk === false ? 'offline' : 'limited');
        if ($probeOk === false && $pingOk === true) {
            $state = 'probe_limited';
        }
        $memUsed = is_numeric($memory['total'] ?? null) && is_numeric($memory['available'] ?? null) ? (float)$memory['total'] - (float)$memory['available'] : null;

        $disks = [];
        $diskMax = null;
        foreach (array_filter(self::arr($probe['disks'] ?? null), 'is_array') as $disk) {
            $pct = is_numeric($disk['used_pct'] ?? null) ? (float)$disk['used_pct'] : null;
            $disks[] = [
                'path' => (string)($disk['path'] ?? '/'),
                'pct' => $pct,
                'free' => is_numeric($disk['free'] ?? null) ? format_bytes($disk['free']) : null,
                'total' => is_numeric($disk['total'] ?? null) ? format_bytes($disk['total']) : null,
                'tone' => $pct !== null && $pct >= $this->th['disk_pct'] ? 'warn' : 'ok',
            ];
            if ($pct !== null) {
                $diskMax = max($diskMax ?? 0.0, $pct);
            }
        }
        $services = [];
        foreach (self::arr($probe['services'] ?? null) as $name => $serviceState) {
            $serviceState = (string)$serviceState;
            $services[] = ['name' => (string)$name, 'state' => $serviceState, 'tone' => $serviceState === 'active' ? 'ok' : 'warn'];
        }

        $cpu = is_numeric($probe['cpu_pct'] ?? null) ? (float)$probe['cpu_pct'] : null;
        $mem = is_numeric($memory['used_pct'] ?? null) ? (float)$memory['used_pct'] : null;
        $io = is_numeric($probe['iowait_pct'] ?? null) ? (float)$probe['iowait_pct'] : null;
        $pingMs = $sla['last_latency_ms'] ?? null;
        $stateTone = $state === 'online' ? 'ok' : 'warn';
        $p = 'm.' . $key . '.';

        return [
            'key' => $key,
            'label' => (string)($raw['label'] ?? $key),
            'location' => (string)($raw['location'] ?? ''),
            'role' => (string)($raw['role'] ?? ''),
            'probe_ok' => $probeOk,
            'ping_ok' => $pingOk,
            'state' => $state,
            'state_tone' => $stateTone,
            'state_text' => $this->put($p . 'state', $this->t('home.machine.state.' . $state), $stateTone),
            'cpu' => $cpu,
            'cpu_tone' => $this->pct($p . 'cpu', $cpu, $cpu !== null && $cpu >= $this->th['cpu_pct'] ? 'warn' : 'ok'),
            'mem' => $mem,
            'mem_tone' => $this->pct($p . 'mem', $mem, $mem !== null && $mem >= $this->th['mem_pct'] ? 'warn' : 'info'),
            'io' => $io,
            'io_text' => $this->put($p . 'io', $io === null ? $this->none() : compact_percent($io)),
            'ping_text' => $this->put($p . 'ping', $pingMs === null || $pingMs === false ? $this->none() : format_latency_ms($pingMs), $pingOk === false ? 'warn' : null),
            'ping_state' => $pingOk === true ? 'reachable' : ($pingOk === false ? 'packet_loss' : 'not_measured'),
            'disk_max' => $diskMax,
            'disk_tone' => $this->pct($p . 'disk', $diskMax, $diskMax !== null && $diskMax >= $this->th['disk_pct'] ? 'warn' : 'ok'),
            'disks' => $disks,
            'sla_text' => $this->put($p . 'sla', is_numeric($sla['month_pct'] ?? null) ? sla_percent($sla['month_pct']) : $this->none()),
            'traffic_today' => $this->put($p . 'traffic', is_numeric($trafficRaw['today_bytes'] ?? null) ? format_bytes($trafficRaw['today_bytes']) : $this->none()),
            'traffic_month' => is_numeric($trafficRaw['month_bytes'] ?? null) ? format_bytes($trafficRaw['month_bytes']) : null,
            'traffic_avg' => is_numeric($trafficRaw['avg_daily_bytes'] ?? null) ? format_bytes($trafficRaw['avg_daily_bytes']) : null,
            'cpu_model' => isset($cpuInfo['model']) ? redact($cpuInfo['model']) : null,
            'cores' => is_scalar($cpuInfo['cores'] ?? null) ? (string)$cpuInfo['cores'] : null,
            'mem_total' => is_numeric($memory['total'] ?? null) ? format_bytes($memory['total']) : null,
            'mem_used' => $memUsed !== null ? format_bytes($memUsed) : null,
            'uptime' => is_numeric($probe['uptime_seconds'] ?? null) ? $this->i18n->duration((int)$probe['uptime_seconds']) : null,
            'services' => $services,
            'error' => !empty($probe['error']) ? redact($probe['error']) : null,
        ];
    }

    /** @param array<string, mixed> $map */
    private function latency(array $map): array
    {
        $nodes = [];
        foreach (self::arr($map['nodes'] ?? null) as $key => $node) {
            if (!is_array($node)) {
                continue;
            }
            $key = normalize_machine_key(['key' => (string)($node['key'] ?? $key)]);
            $label = (string)($node['label'] ?? $key);
            // Matrix headers: config 'short' / 'abbr', else the label without "Server" (3 letters if long).
            $short = trim((string)preg_replace('/\s*server$/i', '', $label)) ?: $label;
            $short = is_string($node['short'] ?? null) && $node['short'] !== '' ? $node['short'] : (mb_strlen($short) > 8 ? mb_substr($short, 0, 3) : $short);
            $nodes[$key] = [
                'key' => $key,
                'label' => $label,
                'short' => $short,
                'abbr' => is_string($node['abbr'] ?? null) && $node['abbr'] !== '' ? $node['abbr'] : mb_substr($short, 0, 3),
                'location' => (string)($node['location'] ?? ''),
                'x' => max(0.0, min(100.0, (float)($node['x'] ?? 50))),
                'y' => max(0.0, min(100.0, (float)($node['y'] ?? 50))),
            ];
        }
        $links = [];
        $matrix = [];
        $values = [];
        foreach (array_filter(self::arr($map['links'] ?? null), 'is_array') as $link) {
            $from = (string)($link['from'] ?? '');
            $to = (string)($link['to'] ?? '');
            if (!isset($nodes[$from], $nodes[$to])) {
                continue;
            }
            $ms = is_numeric($link['latency_ms'] ?? null) ? (float)$link['latency_ms'] : null;
            $ok = $link['ok'] ?? null;
            $tone = $ms === null || $ok === false ? 'warn' : ($ms >= $this->th['latency_warn_ms'] ? 'warn' : ($ms < $this->th['latency_fast_ms'] ? 'ok' : 'neutral'));
            $text = $ms === null ? ($ok === false ? $this->t('home.latency.down') : $this->none()) : self::ms($ms);
            $id = 'lat.' . $from . '.' . $to;
            $this->put($id, $text, $tone);
            $links[] = ['id' => $id, 'from' => $from, 'to' => $to, 'label' => $nodes[$from]['short'] . ' → ' . $nodes[$to]['short'], 'ms' => $ms, 'ok' => $ok, 'tone' => $tone, 'text' => $text];
            $matrix[$from][$to] = $id;
            $matrix[$to][$from] = $id;
            if ($ms !== null) {
                $values[] = $ms;
            }
        }
        sort($values);
        $median = null;
        if ($values !== []) {
            $mid = intdiv(count($values), 2);
            $median = count($values) % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
        }
        return ['enabled' => ($map['enabled'] ?? false) !== false && $nodes !== [], 'nodes' => $nodes, 'links' => $links, 'matrix' => $matrix, 'median' => $median];
    }

    /** Matrix cell text: whole ms from 10 up, one decimal below (like the mockup). */
    private static function ms(float $ms): string
    {
        return $ms < 10 ? number_format($ms, 1) : number_format($ms, 0);
    }

    private function kpi(string $key, string $value, ?string $tone, string $sub): array
    {
        $this->put('kpi.' . $key, $value, $tone ?? 'none');
        $this->put('kpi.' . $key . '.sub', $sub);
        return ['key' => $key, 'label' => $this->t('home.kpi.' . $key), 'value' => $value, 'tone' => $tone, 'sub' => $sub];
    }

    /** A meter value: text + tone + bar width. Returns the tone. */
    private function pct(string $key, ?float $pct, string $tone): string
    {
        $this->live[$key] = ['t' => $pct === null ? $this->none() : compact_percent($pct), 'tone' => $pct === null ? 'neutral' : $tone, 'pct' => $pct === null ? null : round(max(0, min(100, $pct)), 2)];
        return $tone;
    }

    private function put(string $key, string $text, ?string $tone = null, ?float $pct = null): string
    {
        $entry = ['t' => $text];
        if ($tone !== null) {
            $entry['tone'] = $tone;
        }
        if ($pct !== null) {
            $entry['pct'] = round($pct, 2);
        }
        $this->live[$key] = $entry;
        return $text;
    }

    private function endpointLabel(string $label): string
    {
        return $this->t(match ($label) {
            'OK' => 'home.state.ok',
            'Online', 'Reachable' => 'home.state.online',
            'Offline' => 'home.state.offline',
            default => 'home.state.not_configured',
        });
    }

    private function count(mixed $value): string
    {
        return is_numeric($value) ? number_format((float)$value, 0) : $this->none();
    }

    private function rel(mixed $value, int $now): string
    {
        $ts = self::ts($value);
        return $ts === null ? $this->none() : $this->i18n->relative($ts, $now);
    }

    private static function ts(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        $ts = timestamp_value($value);
        return $ts === false ? null : $ts;
    }

    private function none(): string
    {
        return $this->i18n->t('common.value.none');
    }

    private function t(string $key, array $params = []): string
    {
        return $this->i18n->t($key, $params);
    }

    /** @return array<mixed> */
    private static function arr(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
