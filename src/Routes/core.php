<?php
declare(strict_types=1);

// Shared routes (skeleton). Area routes live in their own files (src/Routes/home.php).
use SignalOps\Status\Controllers\Shared\SessionController;

return [
    ['path' => '/logout', 'handler' => [SessionController::class, 'logout'], 'methods' => ['POST']],
];
