<?php
/**
 * Top status strip (overall state, snapshot time, cache state, probes, SLA window), filled from the
 * MONITOR view model (SignalOps\Status\Home\HomeData, read once per request and shared with the
 * page body). Pages may pass their own strip as $page['status'] (Html). The data-live keys are
 * refreshed in place on MONITOR pages. Renders nothing when the home area is not installed.
 * @var SignalOps\Status\View\View $v @var SignalOps\Status\App $app @var array $page
 */
if (!empty($page['status'])) {
    echo $v->e($page['status']);
    return;
}
if (!class_exists(SignalOps\Status\Home\HomeData::class)) {
    return;
}
try {
    $strip = SignalOps\Status\Home\HomeData::get($app)['model'];
    $overall = $strip['overall'];
    $snap = $strip['snapshot'];
    $live = $strip['live'];
} catch (Throwable $error) {
    error_log('[signalops-status] status strip: ' . $error->getMessage());
    return;
}
$reasons = ($overall['reasons'] ?? []) === [] ? $app->t('home.overall.no_reasons') : implode(' · ', array_slice((array)$overall['reasons'], 0, 6));
$liveText = static fn (string $key): string => (string)($live[$key]['t'] ?? $app->t('common.value.none'));
// Only core classes (ui-badge, ui-dot, ui-mono, ui-muted) are guaranteed here.
?>
        <a class="ui-badge strip-pill ui-tone-<?= $v->e($overall['tone'] ?? 'neutral') ?>" href="/overview" title="<?= $v->e($reasons) ?>" data-live-tone="strip.overall" data-live-title="strip.reasons"><span class="ui-dot" aria-hidden="true"></span> <span data-live="strip.overall"><?= $v->e($overall['label'] ?? '') ?></span></a>
        <span class="strip-meta ui-mono ui-muted">
          <span class="strip-item"><?= $v->t('home.strip.snapshot') ?> <strong style="color:var(--text)" data-live="strip.snapshot"><?= $v->e($liveText('strip.snapshot')) ?></strong></span>
          <span aria-hidden="true">·</span>
          <span class="strip-item"><?= $v->t('home.strip.cache') ?> <strong class="ui-tone-<?= $v->e($snap['cache_tone'] ?? 'neutral') ?>" style="color:var(--tone)" data-live="strip.cache" data-live-tone="strip.cache"><?= $v->e($liveText('strip.cache')) ?></strong></span>
          <span aria-hidden="true">·</span>
          <span class="strip-item"><?= $v->t('home.strip.probes') ?> <strong style="color:var(--text)" data-live="strip.probes"><?= $v->e($liveText('strip.probes')) ?></strong></span>
          <span class="strip-sla" aria-hidden="true">·</span>
          <span class="strip-item strip-sla"><?= $v->t('home.strip.sla_window') ?> <strong style="color:var(--text)"><?= $v->t('home.strip.sla_window_value') ?></strong></span>
        </span>
