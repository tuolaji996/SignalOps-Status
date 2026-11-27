<?php
/**
 * Latest Events (newest archived events from the snapshot; text through redact()).
 * @var SignalOps\Status\View\View $v @var SignalOps\Status\View\Ui $ui @var SignalOps\Status\App $app
 * @var array $model @var int|null $limit
 */
$limit ??= null;
$events = $limit === null ? $model['events'] : array_slice($model['events'], 0, $limit);
$rows = [];
foreach ($events as $event) {
    $rows[] = ['cells' => [
        'time' => $event['time'] !== null ? $ui->time($event['time'], 'full') : $event['time_text'],
        'kind' => $ui->raw('<span class="home-kind">' . $v->e($event['kind']) . '</span>'),
        'symbol' => $ui->raw('<strong>' . $v->e($event['symbol']) . '</strong>'),
        'text' => $event['text'],
    ]];
}
?>
<?= $ui->panelStart($app->t('home.events.title'), ['id' => 'events', 'meta' => $limit !== null && count($model['events']) > $limit ? $ui->raw('<a href="/events">' . $v->t('home.events.all', ['n' => count($model['events'])]) . '</a>') : '', 'flush' => true]) ?>
<?= $ui->table([
    ['key' => 'time', 'label' => $app->t('home.events.col.time'), 'mono' => true],
    ['key' => 'kind', 'label' => $app->t('home.events.col.kind')],
    ['key' => 'symbol', 'label' => $app->t('home.events.col.symbol'), 'mono' => true],
    ['key' => 'text', 'label' => $app->t('home.events.col.text'), 'class' => 'ui-wide home-event-text'],
], $rows, ['id' => 'events-table', 'min_width' => 760, 'caption' => $app->t('home.events.title'), 'empty' => $app->t('home.events.empty')]) ?>
<?php if ($model['runtime']['db_error'] !== null): ?>
  <div class="home-pad"><?= $ui->notice('warn', $app->t('home.events.db_error'), $model['runtime']['db_error']) ?></div>
<?php endif; ?>
<?= $ui->panelEnd() ?>
