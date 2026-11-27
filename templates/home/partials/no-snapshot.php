<?php
/** Notice when the status snapshot is missing or old. @var SignalOps\Status\View\View $v @var SignalOps\Status\View\Ui $ui @var SignalOps\Status\App $app @var array $model */
$snap = $model['snapshot'];
if ($snap['state'] !== 'ok'): ?>
  <?= $ui->notice('warn', $app->t('home.snapshot.missing_title'), $app->t('home.snapshot.missing_text')) ?>
<?php elseif ($snap['cache_state'] === 'expired'): ?>
  <?= $ui->notice('warn', $app->t('home.snapshot.old_title', ['age' => $app->i18n->duration((int)$snap['age'])]), $app->t('home.snapshot.old_text')) ?>
<?php endif;
