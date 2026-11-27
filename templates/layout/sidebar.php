<?php
/** @var SignalOps\Status\View\View $v @var SignalOps\Status\View\Ui $ui @var SignalOps\Status\App $app @var array $page */
$active = (string)($page['nav'] ?? '');
?>
  <nav id="nav" class="app-nav" aria-label="<?= $v->t('common.nav.label') ?>">
    <div class="app-nav-head">
      <a class="brand" href="/">
        <svg aria-hidden="true" width="22" height="22" viewBox="0 0 22 22"><rect x="1" y="1" width="20" height="20" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M5 12h3l2-5 3 9 2-4h2" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/></svg>
        <span><?= $v->e($appName ?? $app->t('common.app.name')) ?></span>
      </a>
      <a class="app-nav-close" href="#main"><span class="ui-sr-only"><?= $v->t('common.nav.close') ?></span><svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16"><path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></a>
    </div>
<?php foreach ($app->nav->groups() as $group): ?>
    <div class="nav-group">
      <div class="nav-group-label"><?= $v->t((string)$group['label']) ?></div>
<?php foreach ($group['items'] as $item):
    $isActive = ($item['key'] ?? '') === $active; ?>
      <a class="nav-item<?= $isActive ? ' is-active' : '' ?>" href="<?= $v->e($item['href']) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
        <span><?= $v->t((string)$item['label']) ?></span>
        <span class="nav-item-marks"><?php if (!empty($item['new'])): ?><?= $ui->newBadge() ?><?php endif; ?><?php if (!empty($item['private'])): ?><?= $ui->lockIcon() ?><?php endif; ?></span>
      </a>
<?php endforeach; ?>
    </div>
<?php endforeach; ?>
  </nav>
