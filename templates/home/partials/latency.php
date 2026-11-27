<?php
/**
 * Latency Mesh: 6×6 matrix (default) or a flat map, plus the Latency Links list with a node
 * filter when $withLinks. The map is drawn here (SVG, theme colours via CSS); zoom, pan and
 * filtering live in public/assets/site/home (JS). Works without JS: the view tabs are
 * links (?view=map).
 * @var SignalOps\Status\View\View $v @var SignalOps\Status\View\Ui $ui @var SignalOps\Status\App $app
 * @var array $model @var string $latencyView @var bool $withLinks
 */
$withLinks ??= false;
$lat = $model['latency'];
$nodes = $lat['nodes'];
$th = $model['thresholds'];
$isMap = $latencyView === 'map';
$fmt = static fn (float $n): string => number_format($n, 1, '.', '');
$tabs = '<div class="lat-switch" role="group" aria-label="' . $v->t('home.latency.view') . '">'
    . '<a class="lat-switch-btn' . (!$isMap ? ' is-active' : '') . '" href="?view=matrix" data-lat-view="matrix" aria-pressed="' . (!$isMap ? 'true' : 'false') . '">' . $v->t('home.latency.matrix') . '</a>'
    . '<a class="lat-switch-btn' . ($isMap ? ' is-active' : '') . '" href="?view=map" data-lat-view="map" aria-pressed="' . ($isMap ? 'true' : 'false') . '">' . $v->t('home.latency.map') . '</a></div>';
?>
<?= $ui->panelStart($app->t('home.latency.title'), ['id' => 'latency', 'meta' => $ui->raw($tabs)]) ?>
<?php if (!$lat['enabled'] || $lat['links'] === []): ?>
  <?= $ui->emptyState($app->t('home.latency.empty')) ?>
<?php else: ?>
<div class="lat" data-latency data-view="<?= $isMap ? 'map' : 'matrix' ?>">
  <div class="lat-matrix-wrap" data-lat-pane="matrix"<?= $isMap ? ' hidden' : '' ?>>
    <table class="lat-matrix">
      <caption class="ui-sr-only"><?= $v->t('home.latency.matrix_caption') ?></caption>
      <thead><tr><td></td><?php foreach ($nodes as $node): ?><th scope="col" title="<?= $v->e($node['label']) ?>"><span class="lat-long"><?= $v->e($node['short']) ?></span><span class="lat-abbr" aria-hidden="true"><?= $v->e($node['abbr']) ?></span></th><?php endforeach; ?></tr></thead>
      <tbody>
<?php foreach ($nodes as $from => $rowNode): ?>
        <tr data-lat-row="<?= $v->e($from) ?>">
          <th scope="row" title="<?= $v->e($rowNode['label']) ?>"><span class="lat-long"><?= $v->e($rowNode['short']) ?></span><span class="lat-abbr" aria-hidden="true"><?= $v->e($rowNode['abbr']) ?></span></th>
<?php foreach ($nodes as $to => $colNode):
    if ($from === $to): ?>
          <td class="lat-cell lat-self" aria-label="—">·</td>
<?php continue; endif;
    $id = $lat['matrix'][$from][$to] ?? null;
    $cell = $id !== null ? $model['live'][$id] : ['t' => $app->t('common.value.none'), 'tone' => 'neutral']; ?>
          <td class="lat-cell ui-tone-<?= $v->e($cell['tone'] ?? 'neutral') ?>" title="<?= $v->e($rowNode['short'] . ' → ' . $colNode['short']) ?>" data-link-from="<?= $v->e($from) ?>" data-link-to="<?= $v->e($to) ?>"<?php if ($id !== null): ?> data-live="<?= $v->e($id) ?>" data-live-tone="<?= $v->e($id) ?>"<?php endif; ?>><?= $v->e($cell['t']) ?></td>
<?php endforeach; ?>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="lat-map" data-lat-pane="map" data-lat-map<?= !$isMap ? ' hidden' : '' ?>>
    <div class="lat-map-tools" role="group" aria-label="<?= $v->t('home.latency.map_controls') ?>">
      <button type="button" class="lat-tool" data-map-zoom-in aria-label="<?= $v->t('home.latency.zoom_in') ?>">+</button>
      <button type="button" class="lat-tool" data-map-zoom-out aria-label="<?= $v->t('home.latency.zoom_out') ?>">−</button>
      <button type="button" class="lat-tool lat-tool-text" data-map-reset><?= $v->t('home.latency.reset') ?></button>
    </div>
    <svg class="lat-svg" viewBox="0 0 1000 620" role="img" aria-label="<?= $v->t('home.latency.map_label') ?>">
      <defs>
        <pattern id="lat-dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1.3" class="lat-dot"/></pattern>
      </defs>
      <g data-map-stage>
        <rect class="lat-ground" x="-1000" y="-620" width="3000" height="1860" fill="url(#lat-dots)"/>
        <g class="lat-grid" aria-hidden="true">
<?php for ($x = 100; $x < 1000; $x += 100): ?>
          <line x1="<?= $x ?>" y1="0" x2="<?= $x ?>" y2="620"/>
<?php endfor; for ($y = 124; $y < 620; $y += 124): ?>
          <line x1="0" y1="<?= $y ?>" x2="1000" y2="<?= $y ?>"/>
<?php endfor; ?>
        </g>
        <g class="lat-links">
<?php foreach ($lat['links'] as $i => $link):
    $a = $nodes[$link['from']];
    $b = $nodes[$link['to']];
    $x1 = $a['x'] * 10; $y1 = $a['y'] * 6.2; $x2 = $b['x'] * 10; $y2 = $b['y'] * 6.2;
    $dx = $x2 - $x1; $dy = $y2 - $y1; $dist = max(1.0, sqrt($dx * $dx + $dy * $dy));
    $bend = min(60.0, max(14.0, $dist * 0.12)) * ($i % 2 === 0 ? -1 : 1);
    $cx = ($x1 + $x2) / 2 + (-$dy / $dist) * $bend;
    $cy = ($y1 + $y2) / 2 + ($dx / $dist) * $bend;
    $lx = 0.25 * $x1 + 0.5 * $cx + 0.25 * $x2;
    $ly = 0.25 * $y1 + 0.5 * $cy + 0.25 * $y2;
    $cell = $model['live'][$link['id']]; ?>
          <g class="lat-link ui-tone-<?= $v->e($cell['tone'] ?? 'neutral') ?>" data-link-from="<?= $v->e($link['from']) ?>" data-link-to="<?= $v->e($link['to']) ?>" data-live-tone="<?= $v->e($link['id']) ?>">
            <title><?= $v->e($link['label'] . ': ' . $cell['t']) ?></title>
            <path d="M <?= $fmt($x1) ?> <?= $fmt($y1) ?> Q <?= $fmt($cx) ?> <?= $fmt($cy) ?> <?= $fmt($x2) ?> <?= $fmt($y2) ?>"/>
            <text class="lat-link-label" x="<?= $fmt($lx) ?>" y="<?= $fmt($ly) ?>" data-live="<?= $v->e($link['id']) ?>"><?= $v->e($cell['t']) ?></text>
          </g>
<?php endforeach; ?>
        </g>
        <g class="lat-nodes">
<?php foreach ($nodes as $key => $node):
    $x = $node['x'] * 10; $y = $node['y'] * 6.2;
    // Label on the side away from the nearest node, so close servers do not overlap.
    $near = null;
    foreach ($nodes as $otherKey => $other) {
        $d = $otherKey === $key ? INF : hypot($other['x'] * 10 - $x, $other['y'] * 6.2 - $y);
        if ($d < 140 && ($near === null || $d < $near[0])) {
            $near = [$d, $x - $other['x'] * 10, $y - $other['y'] * 6.2];
        }
    }
    $left = $near !== null ? $near[1] < 0 : $node['x'] > 70;
    [$nameY, $cityY] = $near !== null && $near[2] > 0 ? [26, 41] : ($near !== null ? [-24, -9] : [-4, 12]); ?>
          <g class="lat-node" data-node-key="<?= $v->e($key) ?>" role="button" tabindex="0" aria-pressed="false" aria-label="<?= $v->e($node['label'] . ', ' . $node['location']) ?>" transform="translate(<?= $fmt($x) ?> <?= $fmt($y) ?>)">
            <circle class="lat-node-hit" r="22"/>
            <circle class="lat-node-ring" r="11"/>
            <circle class="lat-node-dot" r="5"/>
            <text class="lat-node-name" x="<?= $left ? -16 : 16 ?>" y="<?= $nameY ?>" text-anchor="<?= $left ? 'end' : 'start' ?>"><?= $v->e($node['short']) ?></text>
            <text class="lat-node-city" x="<?= $left ? -16 : 16 ?>" y="<?= $cityY ?>" text-anchor="<?= $left ? 'end' : 'start' ?>"><?= $v->e(explode(',', $node['location'])[0]) ?></text>
          </g>
<?php endforeach; ?>
        </g>
      </g>
    </svg>
    <p class="lat-hint ui-muted"><?= $v->t('home.latency.map_hint') ?></p>
  </div>
  <p class="lat-legend ui-muted"><?= $v->t('home.latency.legend', ['warn' => (string)(int)$th['latency_warn_ms'], 'fast' => (string)(int)$th['latency_fast_ms']]) ?></p>
<?php if ($withLinks): ?>
  <div class="lat-links-list">
    <div class="lat-links-head">
      <h3 data-lat-focus-title data-title-all="<?= $v->t('home.latency.links_all') ?>" data-title-node="<?= $v->t('home.latency.links_node') ?>"><?= $v->t('home.latency.links_all') ?></h3>
      <span class="ui-muted ui-mono" data-lat-focus-count data-count-label="<?= $v->t('home.latency.paths') ?>"><?= $v->e($app->t('home.latency.paths', ['n' => count($lat['links'])])) ?></span>
    </div>
    <div class="lat-filter" role="group" aria-label="<?= $v->t('home.latency.filter') ?>">
      <button type="button" class="lat-filter-btn is-active" data-lat-filter="all" aria-pressed="true"><?= $v->t('home.latency.all') ?></button>
<?php foreach ($nodes as $key => $node): ?>
      <button type="button" class="lat-filter-btn" data-lat-filter="<?= $v->e($key) ?>" aria-pressed="false"><?= $v->e($node['label']) ?></button>
<?php endforeach; ?>
    </div>
    <ul class="lat-rows">
<?php foreach ($lat['links'] as $link): $cell = $model['live'][$link['id']]; ?>
      <li class="lat-row" data-link-from="<?= $v->e($link['from']) ?>" data-link-to="<?= $v->e($link['to']) ?>">
        <span><?= $v->e($nodes[$link['from']]['label']) ?> <span class="ui-muted">→</span> <?= $v->e($nodes[$link['to']]['label']) ?></span>
        <span><strong class="ui-mono ui-tone-<?= $v->e($cell['tone'] ?? 'neutral') ?>" data-live="<?= $v->e($link['id']) ?>" data-live-tone="<?= $v->e($link['id']) ?>"><?= $v->e($cell['t']) ?></strong><?php if ($link['ms'] !== null): ?> <span class="ui-muted ui-mono">ms</span><?php endif; ?></span>
      </li>
<?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
</div>
<?php endif; ?>
<?= $ui->panelEnd() ?>
