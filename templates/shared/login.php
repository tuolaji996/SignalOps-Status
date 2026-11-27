<?php
/** Inline login for 'auth' routes. Posts back to the same URL (password from config auth.password_hash). */
/** @var SignalOps\Status\View\View $v @var SignalOps\Status\View\Ui $ui @var SignalOps\Status\App $app @var string|null $error @var string $csrf */
?>
<div class="login">
<?= $ui->panelStart($app->t('common.login.title'), ['id' => 'login-title']) ?>
<?php if ($error === 'not_configured'): ?>
  <?= $ui->notice('warn', $app->t('common.login.not_configured'), $app->t('common.login.not_configured_hint')) ?>
<?php else: ?>
  <p class="ui-muted"><?= $v->t('common.login.intro') ?></p>
<?php if ($error !== null): ?>
  <?= $ui->notice('warn', $app->t('common.login.error.' . $error)) ?>
<?php endif; ?>
  <form method="post" action="<?= $v->e($app->request->uriWithout()) ?>" class="login-form">
    <input type="hidden" name="form" value="login">
    <input type="hidden" name="csrf" value="<?= $v->e($csrf) ?>">
    <label for="login-password"><?= $v->t('common.login.password') ?></label>
    <input id="login-password" name="password" type="password" autocomplete="current-password" required autofocus>
    <button type="submit" class="btn btn-primary"><?= $v->t('common.login.submit') ?></button>
  </form>
<?php endif; ?>
<?= $ui->panelEnd() ?>
</div>
