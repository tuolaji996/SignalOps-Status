<?php
/**
 * Shared page layout.
 * @var SignalOps\Status\View\View $v
 * @var SignalOps\Status\App $app
 * @var array $page
 * @var SignalOps\Status\View\Html $content
 */
$dataTheme = $app->prefs->dataTheme();
$title = (string)$page['title'];
$appName = trim((string)$app->config->get('app.name', '')) ?: $app->t('common.app.name');
$fullTitle = ($title !== '' ? $title . ' · ' : '') . $appName;
$repoUrl = trim((string)$app->config->get('app.theme.repository_url', ''));
$ownerName = trim((string)$app->config->get('app.theme.owner_name', ''));
$ownerUrl = trim((string)$app->config->get('app.theme.owner_url', ''));
$isHttpUrl = static fn (string $url): bool => (bool)preg_match('#^https?://#i', $url);
?><!doctype html>
<html lang="<?= $v->e($app->i18n->htmlLang()) ?>"<?= $dataTheme !== null ? ' data-theme="' . $v->e($dataTheme) . '"' : '' ?> data-theme-pref="<?= $v->e($app->prefs->theme) ?>" data-theme-cookie="<?= $v->e(SignalOps\Status\View\Preferences::THEME_COOKIE) ?>" data-timezone="<?= $v->e($app->i18n->timezone) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="<?= $dataTheme ?? 'dark light' ?>">
<?php if (!empty($page['private'])): ?><meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<title><?= $v->e($fullTitle) ?></title>
<?= $v->assets((string)$page['area']) ?>

</head>
<body class="area-<?= $v->e($page['area']) ?>">
<a class="skip-link" href="#main"><?= $v->t('common.a11y.skip') ?></a>
<div class="app">
<?= $v->partial('layout/sidebar', ['page' => $page, 'appName' => $appName]) ?>
  <div class="app-main">
<?= $v->partial('layout/topbar', ['page' => $page, 'appName' => $appName]) ?>
    <main id="main" class="app-content" tabindex="-1">
<?php if ($app->demo()): ?>
      <div class="demo-banner" style="margin-bottom: 16px"><?= $app->view->ui->notice('info', $app->t('common.demo.title'), $app->t('common.demo.text')) ?></div>
<?php endif; ?>
<?php if ($title !== '' && empty($page['hide_heading'])): ?>
      <div class="page-head">
        <div>
          <h1 class="page-title"><?= $v->e($title) ?></h1>
<?php if (!empty($page['subtitle'])): ?>
          <p class="page-subtitle"><?= $v->e($page['subtitle']) ?></p>
<?php endif; ?>
        </div>
<?php if (!empty($page['actions'])): ?>
        <div class="page-actions"><?= $v->e($page['actions']) ?></div>
<?php endif; ?>
      </div>
<?php endif; ?>
<?= $content ?>
      <footer class="app-footer" style="margin-top: 24px; padding: 16px 0 8px; font-size: 12px; color: var(--text-muted)">
        <div>
<?php if ($ownerName !== ''): ?>
          <?= $isHttpUrl($ownerUrl) ? '<a href="' . $v->e($ownerUrl) . '" target="_blank" rel="noopener noreferrer">' . $v->e($ownerName) . '</a>' : $v->e($ownerName) ?> ·
<?php endif; ?>
          <?= $v->t('common.footer.powered_by') ?> <?= $isHttpUrl($repoUrl) ? '<a href="' . $v->e($repoUrl) . '" target="_blank" rel="noopener noreferrer">SignalOps Status</a>' : 'SignalOps Status' ?>
          · <a href="/index.php"><?= $v->t('common.footer.classic') ?></a>
        </div>
<?php $stats = SignalOps\Status\Http\RequestStats::snapshot(); ?>
        <div class="app-footer-stats" data-render-stats style="margin-top: 4px; font-family: var(--font-mono, ui-monospace, monospace); font-size: 11px; opacity: .8"><?= $v->e(implode(' · ', [
            $v->t('common.footer.processed', ['seconds' => sprintf('%.4f', $stats['seconds'])]),
            $v->t('common.footer.cache', ['reads' => $stats['cache_reads'], 'hits' => $stats['cache_hits']]),
            $v->t('common.footer.files', ['count' => $stats['file_reads']]),
            $v->t('common.footer.memory', ['mb' => sprintf('%.1f', $stats['memory_mb'])]),
        ])) ?></div>
      </footer>
    </main>
  </div>
</div>
<?= $v->partial('layout/mobile-tabs', ['page' => $page]) ?>
</body>
</html>
