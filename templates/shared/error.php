<?php
/** @var SignalOps\Status\View\View $v @var SignalOps\Status\View\Ui $ui @var int $code */
?>
<?= $ui->emptyState($app->t('common.error.' . $code . '.title'), $app->t('common.error.' . $code . '.text')) ?>
<p><a class="btn" href="/"><?= $v->t('common.error.home') ?></a></p>
