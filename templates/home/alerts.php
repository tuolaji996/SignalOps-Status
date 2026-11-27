<?php
/**
 * /alerts. Data: SignalOps\Status\Alerts\AlertState (health checker export, or demo sample).
 * @var SignalOps\Status\View\View $v @var SignalOps\Status\View\Ui $ui @var SignalOps\Status\App $app
 * @var array $state @var int $now @var string $filter @var list<string> $filters @var array<string,int> $counts
 * @var list<array> $recent @var int $recovered24h
 */
use SignalOps\Status\Alerts\AlertState;

$i18n = $app->i18n;
$checker = $state['checker'];
$active = $state['active'];
$bySeverity = array_count_values(array_column($active, 'severity'));
$p0 = $bySeverity['P0'] ?? 0;
$p1 = $bySeverity['P1'] ?? 0;
$p23 = ($bySeverity['P2'] ?? 0) + ($bySeverity['P3'] ?? 0);
$events7d = count(array_filter($state['recent'], static fn (array $a): bool => $a['kind'] === 'event'));
$sev = static fn (?string $severity) => $severity === null ? $ui->badge('neutral', $app->t('common.value.none')) : $ui->badge(AlertState::tone($severity), $severity);
?>
<div class="alerts-page">
<?php if ($state['state'] !== 'ok'): ?>
  <?= $ui->notice('warn', $app->t('home.alerts.file.' . $state['state'] . '.title'), $app->t('home.alerts.file.' . $state['state'] . '.text')) ?>
  <?= $ui->panel($app->t('home.alerts.active.title'), $ui->emptyState($app->t('home.alerts.file.unknown_title'), $app->t('home.alerts.file.unknown_text')), ['id' => 'alerts-active']) ?>
<?php else: ?>
<?php if ($checker['last_run'] === null): ?>
  <?= $ui->notice('warn', $app->t('home.alerts.checker.never_title'), $app->t('home.alerts.checker.never_text')) ?>
<?php elseif ($checker['stale']): ?>
  <?= $ui->notice('warn', $app->t('home.alerts.checker.stale_title', ['age' => $i18n->relative($checker['last_run'], $now)]), $app->t('home.alerts.checker.stale_text')) ?>
<?php endif; ?>

  <?= $ui->kpis([
      ['label' => $app->t('home.alerts.kpi.p0'), 'value' => $i18n->number($p0), 'tone' => $p0 > 0 ? 'warn' : 'ok', 'sub' => $app->t('home.alerts.kpi.active_now')],
      ['label' => $app->t('home.alerts.kpi.p1'), 'value' => $i18n->number($p1), 'tone' => $p1 > 0 ? 'warn' : 'ok', 'sub' => $app->t('home.alerts.kpi.active_now')],
      ['label' => $app->t('home.alerts.kpi.p23'), 'value' => $i18n->number($p23), 'tone' => $p23 > 0 ? 'info' : 'ok', 'sub' => $app->t('home.alerts.kpi.active_now')],
      ['label' => $app->t('home.alerts.kpi.recovered_24h'), 'value' => $i18n->number($recovered24h), 'sub' => $app->t('home.alerts.kpi.events_7d', ['n' => $i18n->number($events7d)])],
      ['label' => $app->t('home.alerts.kpi.checker'), 'value' => $checker['last_run'] === null ? $app->t('common.value.none') : $ui->time($checker['last_run'], 'relative', $now), 'tone' => $checker['stale'] ? 'warn' : 'ok',
       'sub' => $app->t('home.alerts.kpi.host', ['host' => $state['host'] !== '' ? $state['host'] : $app->t('common.value.none')])],
  ], $app->t('home.alerts.kpi.label')) ?>

  <?= $ui->panelStart($app->t('home.alerts.active.title'), ['id' => 'alerts-active', 'meta' => $app->t('home.alerts.active.meta'), 'flush' => true]) ?>
<?php if ($active === []): ?>
    <?= $ui->emptyState($app->t('home.alerts.active.empty_title'), $app->t('home.alerts.active.empty_text')) ?>
<?php else: ?>
    <ul class="alert-list">
<?php foreach ($active as $alert): ?>
      <li class="alert-item">
        <div class="alert-sev"><?= $sev($alert['severity']) ?></div>
        <div class="alert-when ui-mono">
          <div><?= $alert['since'] !== null ? $v->e($app->t('home.alerts.since', ['time' => $i18n->datetime($alert['since'], 'short')])) : $v->t('common.value.none') ?></div>
          <div class="ui-muted"><?= $alert['since'] !== null ? $v->e($i18n->duration($now - $alert['since'])) : '' ?></div>
        </div>
        <div class="alert-text">
          <div><strong><?= $v->e($alert['title']) ?></strong> <span class="alert-detail"><?= $v->e($alert['detail']) ?></span></div>
          <div class="alert-meta ui-mono">
            <span><?= $v->e($alert['id']) ?></span>
            <span><?= $v->e($app->t('home.alerts.seen', ['n' => $alert['count']])) ?></span>
<?php if ($alert['last_seen'] !== null): ?>
            <span><?= $v->e($app->t('home.alerts.last_seen', ['age' => $i18n->relative($alert['last_seen'], $now)])) ?></span>
<?php endif; ?>
<?php if ($alert['last_notified'] !== null): ?>
            <span><?= $v->e($app->t('home.alerts.notified', ['age' => $i18n->relative($alert['last_notified'], $now)])) ?></span>
<?php endif; ?>
          </div>
        </div>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  <?= $ui->panelEnd() ?>

<?php
$tabs = [];
foreach ($filters as $f) {
    $tabs[] = ['label' => $app->t('home.alerts.filter.' . $f), 'href' => $f === 'all' ? '/alerts#alerts-recent' : '/alerts?filter=' . $f . '#alerts-recent', 'active' => $f === $filter, 'count' => $counts[$f] ?? 0];
}
$rows = [];
foreach ($recent as $alert) {
    $isRecovery = $alert['kind'] === 'recovered';
    $rows[] = [
        'cells' => [
            'kind' => $ui->badge($isRecovery ? 'ok' : AlertState::tone($alert['severity']), $app->t('home.alerts.kind.' . $alert['kind'])),
            'severity' => $sev($alert['severity']),
            'at' => $alert['at'] !== null ? $ui->time($alert['at'], 'short') : '',
            'duration' => $isRecovery && $alert['since'] !== null && $alert['at'] !== null ? $i18n->duration($alert['at'] - $alert['since']) : '',
            'title' => $alert['title'],
        ],
        'detail' => $ui->facts([
            ['label' => $app->t('home.alerts.col.detail'), 'value' => $alert['detail'] !== '' ? $alert['detail'] : $app->t('common.value.none')],
            ['label' => $app->t('home.alerts.col.id'), 'value' => $alert['id'], 'mono' => true],
            ['label' => $app->t('home.alerts.col.since'), 'value' => $alert['since'] !== null ? $i18n->datetime($alert['since'], 'full') : $app->t('common.value.none'), 'mono' => true],
            ['label' => $app->t('home.alerts.col.at'), 'value' => $alert['at'] !== null ? $i18n->datetime($alert['at'], 'full') : $app->t('common.value.none'), 'mono' => true],
        ]),
    ];
}
?>
  <?= $ui->panelStart($app->t('home.alerts.recent.title'), ['id' => 'alerts-recent', 'meta' => $app->t('home.alerts.recent.meta'), 'flush' => true]) ?>
    <?= $ui->tabs($tabs, $app->t('home.alerts.filter.label')) ?>
    <?= $ui->table([
        ['key' => 'kind', 'label' => $app->t('home.alerts.col.kind')],
        ['key' => 'severity', 'label' => $app->t('home.alerts.col.severity')],
        ['key' => 'at', 'label' => $app->t('home.alerts.col.at'), 'mono' => true],
        ['key' => 'duration', 'label' => $app->t('home.alerts.col.duration'), 'mono' => true, 'align' => 'right'],
        ['key' => 'title', 'label' => $app->t('home.alerts.col.title'), 'class' => 'ui-wide'],
    ], $rows, ['id' => 'alerts-recent-table', 'min_width' => 720, 'caption' => $app->t('home.alerts.recent.title'), 'empty' => $app->t('home.alerts.recent.empty')]) ?>
  <?= $ui->panelEnd() ?>
<?php endif; ?>
  <p class="page-foot ui-muted ui-mono"><?= $v->e($app->t('home.alerts.source', ['path' => basename($state['path'])])) ?><?php if ($state['generated_at'] !== null): ?> · <?= $v->e($app->t('home.alerts.generated', ['time' => $i18n->datetime($state['generated_at'], 'full')])) ?><?php endif; ?></p>
</div>
