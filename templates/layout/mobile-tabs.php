<?php
/** Phone bottom tab bar (≤ 760px). Tabs come from 'mobile_tabs' in src/Nav/<area>.php. */
/** @var SignalOps\Status\View\View $v @var SignalOps\Status\App $app @var array $page */
$active = (string)($page['nav'] ?? '');
$activeItem = $active !== '' ? $app->nav->item($active) : null;
$tabs = $app->nav->mobileTabs();
// Active tab: exact nav key; otherwise the area's tab when the area has exactly one; otherwise "More".
$activeIndex = null;
foreach ($tabs as $i => $tab) {
    if ($tab['key'] === $active) {
        $activeIndex = $i;
    }
}
if ($activeIndex === null && $activeItem !== null) {
    $sameArea = array_keys(array_filter($tabs, static fn (array $tab): bool => $tab['area'] === $activeItem['area']));
    $activeIndex = count($sameArea) === 1 ? $sameArea[0] : null;
}
?>
<nav class="mobile-tabs" aria-label="<?= $v->t('common.nav.tabs') ?>">
<?php foreach ($tabs as $i => $tab): $isActive = $i === $activeIndex; ?>
  <a class="mobile-tab<?= $isActive ? ' is-active' : '' ?>" href="<?= $v->e($tab['href']) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>><?= $v->t((string)$tab['label']) ?></a>
<?php endforeach; ?>
  <a class="mobile-tab<?= $activeIndex === null && $active !== '' ? ' is-active' : '' ?>" href="#nav"><?= $v->t('common.nav.more') ?></a>
</nav>
