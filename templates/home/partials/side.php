<?php
/**
 * Right column of the Overview: Service Health (bot + API endpoints), Bot Error Journal, Archive
 * Breakdown, Traffic Summary. $sections limits which ones are shown.
 * @var SignalOps\Status\View\View $v @var SignalOps\Status\View\Ui $ui @var SignalOps\Status\App $app
 * @var array $model @var list<string> $sections
 */
use SignalOps\Status\Home\MonitorView as MV;

$sections ??= ['endpoints', 'journal', 'archive', 'traffic'];
$journal = $model['journal'];
$entriesLimit ??= 3;
$stat = static fn (string $label, $value): string => '<div class="home-stat"><div class="home-stat-label">' . htmlspecialchars($label, ENT_QUOTES) . '</div><div class="home-stat-value">' . $value . '</div></div>';
?>
<?php if (in_array('endpoints', $sections, true)): ?>
<?= $ui->panelStart($app->t('home.endpoint.title'), ['id' => 'service-health']) ?>
<?php foreach ($model['endpoints'] as $key => $ep): $p = 'ep.' . $key . '.'; ?>
  <div class="home-endpoint">
    <div class="home-endpoint-head"><strong><?= $v->e($ep['name']) ?></strong> <?= MV::live($p . 'state', $ep['state'], $ep['tone'], 'home-state') ?></div>
    <div class="home-stats">
      <?= $stat($ep['activity_label'], MV::live($p . 'activity', $ep['activity'])) ?>
      <?= $stat($ep['count_label'], MV::live($p . 'count', $ep['count'])) ?>
      <?= $stat($app->t('home.endpoint.http'), MV::live($p . 'http', $ep['http'])) ?>
    </div>
  </div>
<?php endforeach; ?>
<?= $ui->panelEnd() ?>
<?php endif; ?>

<?php if (in_array('journal', $sections, true)): ?>
<?= $ui->panelStart($app->t('home.journal.title'), ['id' => 'discord-journal', 'meta' => MV::live('journal.state', $journal['label'], $journal['tone'], 'home-journal-state')]) ?>
  <p class="home-line ui-mono">
    <span class="ui-muted"><?= $v->t($journal['recovered'] ? 'home.journal.hist_warnings' : 'home.journal.warnings') ?></span> <?= MV::live('journal.warnings', $journal['warnings']) ?>
    <span class="ui-muted" aria-hidden="true">·</span>
    <span class="ui-muted"><?= $v->t($journal['recovered'] ? 'home.journal.hist_errors' : 'home.journal.errors') ?></span> <?= MV::live('journal.errors', $journal['errors']) ?>
    <span class="ui-muted" aria-hidden="true">·</span>
    <span class="ui-muted"><?= $v->t($journal['recovered'] ? 'home.journal.latest_incident' : 'home.journal.latest') ?></span> <?= MV::live('journal.latest', $journal['latest']) ?>
  </p>
<?php if ($journal['entries'] !== []): ?>
  <ul class="home-journal">
<?php foreach (array_slice($journal['entries'], 0, $entriesLimit) as $entry): $tone = $entry['kind'] === 'recovered' ? 'ok' : ($entry['kind'] === 'error' ? 'warn' : 'info'); ?>
    <li>
      <span class="ui-mono ui-muted"><?= $entry['time'] !== null ? $ui->time($entry['time'], 'datetime') : '' ?></span>
      <?= $ui->badge($tone, $app->t('home.journal.kind.' . $entry['kind'])) ?>
      <span class="home-journal-msg ui-mono"><?= $v->e($entry['message']) ?></span>
    </li>
<?php endforeach; ?>
  </ul>
<?php endif; ?>
<?= $ui->panelEnd() ?>
<?php endif; ?>

<?php if (in_array('archive', $sections, true)): ?>
<?= $ui->panelStart($app->t('home.archive.title'), ['id' => 'archive']) ?>
<?php if ($model['archive']['kinds'] === []): ?>
  <p class="ui-muted"><?= $v->t('home.archive.empty') ?></p>
<?php else: ?>
  <ul class="home-bars">
<?php foreach ($model['archive']['kinds'] as $i => $kind): ?>
    <li>
      <span class="home-bar-label"><?= $v->e($kind['kind']) ?></span>
      <span class="home-bar-track" aria-hidden="true"><span class="home-bar-fill" data-live-bar="archive.kind.<?= $i ?>" style="width:<?= round($kind['pct'], 2) ?>%"></span></span>
      <span class="home-bar-value ui-mono" data-live="archive.kind.<?= $i ?>"><?= $v->e(number_format($kind['count'])) ?></span>
    </li>
<?php endforeach; ?>
  </ul>
<?php endif; ?>
  <p class="home-line ui-mono"><span class="ui-muted"><?= $v->t('home.archive.symbols') ?></span> <?= MV::live('archive.symbols', $model['archive']['symbols']) ?></p>
<?= $ui->panelEnd() ?>
<?php endif; ?>

<?php if (in_array('traffic', $sections, true)): ?>
<?= $ui->panelStart($app->t('home.traffic.title'), ['id' => 'traffic']) ?>
  <p class="home-line ui-mono">
    <span class="ui-muted"><?= $v->t('home.traffic.today') ?></span> <?= MV::live('traffic.today', $model['traffic']['today']) ?>
    <span class="ui-muted" aria-hidden="true">·</span>
    <span class="ui-muted"><?= $v->t('home.traffic.month') ?></span> <?= MV::live('traffic.month', $model['traffic']['month']) ?>
    <span class="ui-muted" aria-hidden="true">·</span>
    <span class="ui-muted"><?= $v->t('home.traffic.avg') ?></span> <?= MV::live('traffic.avg', $model['traffic']['avg']) ?>
  </p>
<?= $ui->panelEnd() ?>
<?php endif; ?>
