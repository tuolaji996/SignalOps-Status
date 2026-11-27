<?php
/**
 * Runtime Details (bot and API counters from their health endpoints).
 * @var SignalOps\Status\View\View $v @var SignalOps\Status\View\Ui $ui @var SignalOps\Status\App $app @var array $model
 */
use SignalOps\Status\Home\MonitorView as MV;

$rt = $model['runtime'];
$items = [
    ['home.runtime.poll_count', 'rt.poll_count', $rt['poll_count']],
    ['home.runtime.publish_count', 'rt.publish_count', $rt['publish_count']],
    ['home.runtime.last_event', 'rt.last_event', $rt['last_event']],
    ['home.runtime.bot_capture', 'rt.bot_capture', $rt['bot_capture']],
    ['home.runtime.change_count', 'rt.change_count', $rt['change_count']],
    ['home.runtime.http', 'rt.http', $rt['http']],
    ['home.runtime.api_capture', 'rt.api_capture', $rt['api_capture']],
];
?>
<?= $ui->panelStart($app->t('home.runtime.title'), ['id' => 'runtime']) ?>
  <p class="home-line home-runtime ui-mono">
<?php foreach ($items as $i => [$label, $key, $value]): ?>
    <span class="home-runtime-item"><span class="ui-muted"><?= $v->t($label) ?></span> <?= MV::live($key, $value) ?></span><?php if ($i < count($items) - 1): ?><span class="ui-muted home-sep" aria-hidden="true">·</span><?php endif; ?>

<?php endforeach; ?>
  </p>
<?php foreach ($rt['notes'] as $note): ?>
  <p class="home-note ui-muted"><?= $v->e($note) ?></p>
<?php endforeach; ?>
<?= $ui->panelEnd() ?>
