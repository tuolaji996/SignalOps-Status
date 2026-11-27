<?php
declare(strict_types=1);

namespace SignalOps\Status\Controllers\Shared;

use SignalOps\Status\Controllers\Controller;
use SignalOps\Status\Http\Response;
use SignalOps\Status\Kernel;

final class SessionController extends Controller
{
    /** POST /logout with the layout's CSRF token. Ends the shared session (old pages too). */
    public function logout(array $params = []): Response
    {
        $this->app->auth->start();
        if (!$this->app->auth->verifyCsrf($this->request->post('csrf'))) {
            return Response::redirect('/', 303);
        }
        $this->app->auth->logout();
        return Response::redirect(Kernel::safeReturn($this->request->post('return'), '/'), 303)->private();
    }
}
