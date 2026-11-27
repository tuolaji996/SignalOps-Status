<?php
declare(strict_types=1);

/*
 * PSR-4 autoloader for the SignalOps\Status\ namespace (src/).
 *
 * The project ships without Composer or vendor/, so this is the runtime loader.
 * Class SignalOps\Status\Http\Router lives in src/Http/Router.php.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'SignalOps\\Status\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    if ($relative === '' || preg_match('/[^A-Za-z0-9_\\\\]/', $relative)) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
