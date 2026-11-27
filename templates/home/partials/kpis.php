<?php
/**
 * Service Overview: the 8 metric tiles. Same look as Ui::kpis(), plus data-live keys for the
 * 60 s refresh.
 * @var SignalOps\Status\View\View $v @var SignalOps\Status\App $app @var array $model
 */
?>
<section class="ui-kpis home-kpis" aria-label="<?= $v->t('home.kpi.label') ?>">
<?php foreach ($model['kpis'] as $kpi): $key = 'kpi.' . $kpi['key']; ?>
  <div class="ui-kpi">
    <div class="ui-kpi-label"><?= $v->e($kpi['label']) ?></div>
    <div class="ui-kpi-value<?= $kpi['tone'] !== null ? ' ui-tone-' . $v->e($kpi['tone']) : '' ?>" data-live="<?= $v->e($key) ?>" data-live-tone="<?= $v->e($key) ?>"><?= $v->e($kpi['value']) ?></div>
    <div class="ui-kpi-sub" data-live="<?= $v->e($key) ?>.sub"><?= $v->e($kpi['sub']) ?></div>
  </div>
<?php endforeach; ?>
</section>
