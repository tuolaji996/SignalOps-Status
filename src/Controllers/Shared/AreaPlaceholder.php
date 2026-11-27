<?php
declare(strict_types=1);

namespace SignalOps\Status\Controllers\Shared;

use SignalOps\Status\Controllers\Controller;
use SignalOps\Status\Http\Response;

/** "Coming soon" page in the new layout, linking to the old page (route 'legacy'). */
abstract class AreaPlaceholder extends Controller
{
    public function show(array $params = []): Response
    {
        return $this->page('shared/placeholder', [
            'legacy' => isset($this->route['legacy']) ? (string)$this->route['legacy'] : null,
        ]);
    }

    /** Area index (e.g. /<area>): go to the first page of the area. */
    public function index(array $params = []): Response
    {
        foreach ($this->app->nav->groups() as $group) {
            if ($group['area'] === $this->area() && isset($group['items'][0]['href'])) {
                return Response::redirect((string)$group['items'][0]['href'], 302);
            }
        }
        return Response::redirect('/', 302);
    }
}
