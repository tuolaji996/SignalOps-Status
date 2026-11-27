<?php
/** @var SignalOps\Status\View\View $v @var SignalOps\Status\App $app @var array $page @var string $appName */
$prefs = $app->prefs;
$request = $app->request;
$now = time();
?>
    <header class="topbar">
      <a class="brand topbar-brand" href="/"><?= $v->e($appName ?? $app->t('common.app.name')) ?></a>
      <div class="topbar-status">
<?= $v->partial('layout/status-strip', ['page' => $page]) ?>
      </div>
      <div class="topbar-tools">
        <time class="topbar-clock" data-clock data-clock-tz="<?= $v->e($app->i18n->timezone) ?>" datetime="<?= gmdate('Y-m-d\TH:i:s\Z', $now) ?>" title="<?= $v->e($app->i18n->timezone) ?>"><span class="topbar-clock-date" data-clock-date><?= $v->e($app->i18n->datetime($now, 'date')) ?></span> <span data-clock-time><?= $v->e($app->i18n->datetime($now, 'clock')) ?></span></time>
        <a class="btn btn-quiet" href="<?= $v->e($request->uriWith(['lang' => $prefs->otherLocale()])) ?>" hreflang="<?= $v->e($prefs->otherLocale()) ?>" lang="<?= $v->e($prefs->otherLocale()) ?>" data-lang-switch="<?= $v->e($prefs->otherLocale()) ?>"><?= $v->t('common.lang.switch_to') ?></a>
        <a class="btn btn-quiet" href="<?= $v->e($request->uriWith(['theme' => $prefs->nextTheme()])) ?>" data-theme-toggle
           data-label-system="<?= $v->t('common.theme.system') ?>" data-label-light="<?= $v->t('common.theme.light') ?>" data-label-dark="<?= $v->t('common.theme.dark') ?>"><?= $v->t('common.theme.' . $prefs->theme) ?></a>
<?php if ($app->authenticated): ?>
        <form class="topbar-logout" method="post" action="/logout">
          <input type="hidden" name="csrf" value="<?= $v->e($app->csrf) ?>">
          <input type="hidden" name="return" value="<?= $v->e($request->path) ?>">
          <button type="submit" class="btn btn-quiet"><?= $v->t('common.login.logout') ?></button>
        </form>
<?php endif; ?>
      </div>
    </header>
