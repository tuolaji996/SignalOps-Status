<?php
/**
 * Server Resource Monitor: one row per machine, click for details. On phones a compact list
 * replaces the wide table.
 * @var SignalOps\Status\View\View $v @var SignalOps\Status\View\Ui $ui @var SignalOps\Status\App $app
 * @var array $model
 */
use SignalOps\Status\Home\MonitorView;

$live = $model['live'];
$rows = [];
foreach ($model['machines'] as $m) {
    $p = 'm.' . $m['key'] . '.';
    $rows[] = [
        'tone' => $m['state_tone'] === 'ok' ? null : 'warn',
        'cells' => [
            'server' => $ui->raw('<div class="home-server-name">' . $v->e($m['label']) . '</div><div class="home-server-loc">' . $v->e($m['location']) . '</div>'),
            'state' => $ui->raw('<span class="home-state ui-tone-' . $v->e($m['state_tone']) . '" data-live="' . $p . 'state" data-live-tone="' . $p . 'state">' . $v->e($m['state_text']) . '</span>'),
            'cpu' => MonitorView::meter($ui, $live, $p . 'cpu', $m['cpu'], $m['cpu_tone']),
            'mem' => MonitorView::meter($ui, $live, $p . 'mem', $m['mem'], $m['mem_tone']),
            'io' => $ui->raw('<span data-live="' . $p . 'io">' . $v->e($m['io_text']) . '</span>'),
            'ping' => $ui->raw('<span data-live="' . $p . 'ping">' . $v->e($m['ping_text']) . '</span>'),
            'disk' => MonitorView::meter($ui, $live, $p . 'disk', $m['disk_max'], $m['disk_tone']),
            'sla' => $ui->raw('<span data-live="' . $p . 'sla">' . $v->e($m['sla_text']) . '</span>'),
            'traffic' => $ui->raw('<span data-live="' . $p . 'traffic">' . $v->e($m['traffic_today']) . '</span>'),
        ],
        'detail' => MonitorView::serverDetail($app, $ui, $m),
    ];
}
?>
<?= $ui->panelStart($app->t('home.servers.title'), ['id' => 'servers', 'meta' => $app->t('home.servers.meta'), 'flush' => true]) ?>
<div class="home-servers-table">
<?= $ui->table([
    ['key' => 'server', 'label' => $app->t('home.servers.col.server')],
    ['key' => 'state', 'label' => $app->t('home.servers.col.state')],
    ['key' => 'cpu', 'label' => $app->t('home.servers.col.cpu'), 'class' => 'home-col-meter'],
    ['key' => 'mem', 'label' => $app->t('home.servers.col.mem'), 'class' => 'home-col-meter'],
    ['key' => 'io', 'label' => $app->t('home.servers.col.io'), 'mono' => true],
    ['key' => 'ping', 'label' => $app->t('home.servers.col.ping'), 'mono' => true],
    ['key' => 'disk', 'label' => $app->t('home.servers.col.disk'), 'class' => 'home-col-meter'],
    ['key' => 'sla', 'label' => $app->t('home.servers.col.sla'), 'mono' => true],
    ['key' => 'traffic', 'label' => $app->t('home.servers.col.traffic'), 'mono' => true],
], $rows, ['id' => 'servers-table', 'min_width' => 980, 'caption' => $app->t('home.servers.title'), 'empty' => $app->t('home.servers.empty')]) ?>
</div>
<?php if ($model['machines'] !== []): ?>
<ul class="home-servers-compact" aria-label="<?= $v->t('home.servers.title') ?>">
  <li class="home-compact-head ui-mono" aria-hidden="true"><span></span><span><?= $v->t('home.servers.col.cpu') ?></span><span><?= $v->t('home.servers.col.mem') ?></span><span><?= $v->t('home.servers.col.disk_short') ?></span></li>
<?php foreach ($model['machines'] as $m): $p = 'm.' . $m['key'] . '.'; ?>
  <li class="home-compact-row">
    <a href="/servers#server-<?= $v->e($m['key']) ?>">
      <span class="home-compact-name"><span class="home-server-name"><?= $v->e($m['label']) ?></span>
        <span class="home-server-loc"><?= $v->e(explode(',', $m['location'])[0]) ?> · <span data-live="<?= $p ?>ping"><?= $v->e($m['ping_text']) ?></span><?php if ($m['state_tone'] !== 'ok'): ?> · <span class="ui-tone-warn home-state"><?= $v->e($m['state_text']) ?></span><?php endif; ?></span></span>
<?php foreach (['cpu', 'mem', 'disk'] as $metric): $tone = $m[$metric . '_tone']; ?>
      <span class="home-compact-num ui-mono<?= $tone === 'warn' ? ' ui-tone-warn' : '' ?>" data-live="<?= $p . $metric ?>" data-live-tone="<?= $p . $metric ?>" data-live-tone-only-warn><?= $v->e($live[$p . $metric]['t'] ?? '') ?></span>
<?php endforeach; ?>
    </a>
  </li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?= $ui->panelEnd() ?>
