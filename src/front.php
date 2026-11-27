<?php
declare(strict_types=1);

/*
 * Front controller for the routed pages. public/index.php hands every path except "/index.php"
 * (and "/?classic=1") to this file; the web server sends paths that are not real files to
 * public/index.php (nginx: try_files $uri $uri/ /index.php?$query_string).
 */

$statusRoot = dirname(__DIR__);
require $statusRoot . '/app/bootstrap.php';
require __DIR__ . '/autoload.php';
SignalOps\Status\Http\RequestStats::reset();

$statusRequest = SignalOps\Status\Http\Request::fromGlobals();
$statusResponse = SignalOps\Status\Kernel::boot($statusRoot)->handle($statusRequest);
$statusResponse->send($statusRequest->method === 'HEAD');
exit;
