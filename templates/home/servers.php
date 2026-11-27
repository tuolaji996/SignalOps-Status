<?php
/**
 * /servers — machine table plus every machine's details.
 * @var SignalOps\Status\View\View $v @var SignalOps\Status\View\Ui $ui @var SignalOps\Status\App $app
 * @var array $model @var int $refreshSeconds
 */
use SignalOps\Status\Home\MonitorView;

$live = $model['live'];
?>
<div class="home-page" data-home-live="/overview/live" data-refresh="<?= (int)$refreshSeconds ?>">
<?= $v->partial('home/partials/no-snapshot', ['model' => $model]) ?>
<?= $v->partial('home/partials/servers', ['model' => $model]) ?>
  <div class="home-cards">
<?php foreach ($model['machines'] as $m): $p = 'm.' . $m['key'] . '.'; ?>
    <?= $ui->panelStart($m['label'], ['id' => 'server-' . $m['key'], 'meta' => MonitorView::live($p . 'state', $m['state_text'], $m['state_tone'], 'home-state')]) ?>
      <div class="home-card-loc ui-muted"><?= $v->e($m['location']) ?></div>
      <div class="home-card-meters">
        <div><span class="home-card-meter-label"><?= $v->t('home.servers.col.cpu') ?></span><?= MonitorView::meter($ui, $live, $p . 'cpu', $m['cpu'], $m['cpu_tone']) ?></div>
        <div><span class="home-card-meter-label"><?= $v->t('home.servers.col.mem') ?></span><?= MonitorView::meter($ui, $live, $p . 'mem', $m['mem'], $m['mem_tone']) ?></div>
        <div><span class="home-card-meter-label"><?= $v->t('home.servers.col.disk_short') ?></span><?= MonitorView::meter($ui, $live, $p . 'disk', $m['disk_max'], $m['disk_tone']) ?></div>
        <div><span class="home-card-meter-label"><?= $v->t('home.servers.col.io') ?></span><?= MonitorView::live($p . 'io', $m['io_text'], null, 'ui-mono') ?></div>
      </div>
      <?= MonitorView::serverDetail($app, $ui, $m) ?>
    <?= $ui->panelEnd() ?>
<?php endforeach; ?>
  </div>
<?= $v->partial('home/partials/side', ['model' => $model, 'sections' => ['traffic']]) ?>
</div>
