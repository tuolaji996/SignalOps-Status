<?php
/**
 * /latency — matrix / map and the Latency Links list with a node filter.
 * @var SignalOps\Status\View\View $v @var array $model @var string $latencyView @var int $refreshSeconds
 */
?>
<div class="home-page" data-home-live="/overview/live" data-refresh="<?= (int)$refreshSeconds ?>">
<?= $v->partial('home/partials/no-snapshot', ['model' => $model]) ?>
<?= $v->partial('home/partials/latency', ['model' => $model, 'latencyView' => $latencyView, 'withLinks' => true]) ?>
</div>
