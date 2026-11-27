<?php
declare(strict_types=1);

namespace SignalOps\Status\Home;

use SignalOps\Status\App;

/**
 * The MONITOR view model for one request (snapshot read + StatusModel), built at most once per
 * request: the status strip on every page and the page body share it.
 */
final class HomeData
{
    /** @var \WeakMap<App, array{snap: array<string, mixed>, model: array<string, mixed>}>|null */
    private static ?\WeakMap $memo = null;

    /** @return array{snap: array<string, mixed>, model: array<string, mixed>} */
    public static function get(App $app, ?int $now = null): array
    {
        self::$memo ??= new \WeakMap();
        if (!isset(self::$memo[$app])) {
            $now ??= time();
            $snap = StatusSnapshot::read($app->config->all(), $now);
            $model = (new StatusModel($app->i18n, (array)$app->config->get('home.thresholds', [])))->build($snap, $now);
            self::$memo[$app] = ['snap' => $snap, 'model' => $model];
        }
        return self::$memo[$app];
    }
}
