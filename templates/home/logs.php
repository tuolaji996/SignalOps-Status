<?php
/**
 * /logs — bot error journal (all entries in the snapshot), probe errors, runtime notes.
 * @var SignalOps\Status\View\View $v @var SignalOps\Status\View\Ui $ui @var SignalOps\Status\App $app
 * @var array $model @var int $refreshSeconds
 */
$probeErrors = array_values(array_filter($model['machines'], static fn (array $m): bool => $m['error'] !== null));
?>
<div class="home-page" data-home-live="/overview/live" data-refresh="<?= (int)$refreshSeconds ?>">
<?= $v->partial('home/partials/no-snapshot', ['model' => $model]) ?>
  <div class="home-grid home-grid-even">
    <div class="home-main">
<?= $v->partial('home/partials/side', ['model' => $model, 'sections' => ['journal'], 'entriesLimit' => 50]) ?>
<?php if ($model['journal']['entries'] === []): ?>
      <p class="ui-muted home-note"><?= $v->t('home.logs.no_entries') ?></p>
<?php endif; ?>
    </div>
    <div class="home-side">
<?= $ui->panelStart($app->t('home.logs.probes_title'), ['id' => 'probe-errors']) ?>
<?php if ($probeErrors === []): ?>
      <p class="ui-muted"><?= $v->t('home.logs.probes_ok') ?></p>
<?php else: ?>
      <ul class="home-journal">
<?php foreach ($probeErrors as $m): ?>
        <li><?= $ui->badge('warn', $m['state_text']) ?> <strong><?= $v->e($m['label']) ?></strong> <span class="home-journal-msg ui-mono"><?= $v->e($m['error']) ?></span></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
<?= $ui->panelEnd() ?>
<?= $v->partial('home/partials/side', ['model' => $model, 'sections' => ['endpoints']]) ?>
    </div>
  </div>
<?= $v->partial('home/partials/runtime', ['model' => $model]) ?>
</div>
