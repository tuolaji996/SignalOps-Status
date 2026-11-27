<?php
/**
 * /events — every recent archived event in the snapshot, Archive Breakdown.
 * @var SignalOps\Status\View\View $v @var array $model @var int $refreshSeconds
 */
?>
<div class="home-page" data-home-live="/overview/live" data-refresh="<?= (int)$refreshSeconds ?>">
<?= $v->partial('home/partials/no-snapshot', ['model' => $model]) ?>
<?= $v->partial('home/partials/events', ['model' => $model]) ?>
<?= $v->partial('home/partials/side', ['model' => $model, 'sections' => ['archive']]) ?>
</div>
