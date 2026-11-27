<?php
declare(strict_types=1);

// MONITOR area routes. Data only; see src/Http/Router.php for the keys.
// '/' is the overview; the classic dashboard stays at /index.php ('legacy' links point there).
// /alerts is login-gated ('auth' => true); in demo mode without a configured password the Kernel
// opens it so the sample alerts are visible.
use SignalOps\Status\Controllers\Home\AlertsController;
use SignalOps\Status\Controllers\Home\MonitorController;

return [
    ['path' => '/', 'handler' => [MonitorController::class, 'overview'], 'nav' => 'home.overview'],
    ['path' => '/overview', 'handler' => [MonitorController::class, 'overview'], 'nav' => 'home.overview', 'legacy' => '/index.php?classic=1'],
    ['path' => '/overview/live', 'handler' => [MonitorController::class, 'live']],
    ['path' => '/servers', 'handler' => [MonitorController::class, 'servers'], 'nav' => 'home.servers', 'legacy' => '/index.php?classic=1'],
    ['path' => '/latency', 'handler' => [MonitorController::class, 'latency'], 'nav' => 'home.latency', 'legacy' => '/index.php?classic=1'],
    ['path' => '/logs', 'handler' => [MonitorController::class, 'logs'], 'nav' => 'home.logs', 'legacy' => '/index.php?classic=1'],
    ['path' => '/events', 'handler' => [MonitorController::class, 'events'], 'nav' => 'home.events', 'legacy' => '/index.php?classic=1'],
    ['path' => '/alerts', 'handler' => [AlertsController::class, 'index'], 'nav' => 'home.alerts', 'auth' => true],
];
