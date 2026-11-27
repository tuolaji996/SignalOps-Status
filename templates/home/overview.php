<?php
/**
 * /overview (also /) — the public status page.
 * @var SignalOps\Status\View\View $v @var SignalOps\Status\View\Ui $ui @var SignalOps\Status\App $app
 * @var array $model @var string $latencyView @var int $refreshSeconds
 */
?>
<div class="home-page" data-home-live="/overview/live" data-refresh="<?= (int)$refreshSeconds ?>">
<?= $v->partial('home/partials/no-snapshot', ['model' => $model]) ?>
<?= $v->partial('home/partials/kpis', ['model' => $model]) ?>
<?= $v->partial('home/partials/servers', ['model' => $model]) ?>
  <div class="home-grid">
    <div class="home-main">
<?= $v->partial('home/partials/latency', ['model' => $model, 'latencyView' => $latencyView]) ?>
    </div>
    <div class="home-side">
<?= $v->partial('home/partials/side', ['model' => $model]) ?>
    </div>
  </div>
<?= $v->partial('home/partials/events', ['model' => $model, 'limit' => 5]) ?>
<?= $v->partial('home/partials/runtime', ['model' => $model]) ?>
</div>
