<?php
/** "Coming soon" page for routes that are registered but not built yet. */
/** @var SignalOps\Status\View\View $v @var SignalOps\Status\View\Ui $ui @var SignalOps\Status\App $app @var string|null $legacy */
?>
<section class="ui-panel">
  <div class="ui-empty">
    <div class="ui-empty-title"><?= $v->t('common.placeholder.title') ?></div>
    <div class="ui-empty-text"><?= $v->t($legacy !== null ? 'common.placeholder.text_legacy' : 'common.placeholder.text_new') ?></div>
<?php if ($legacy !== null): ?>
    <p><a class="btn" href="<?= $v->e($legacy) ?>"><?= $v->t('common.placeholder.open_legacy') ?></a></p>
<?php endif; ?>
  </div>
</section>
