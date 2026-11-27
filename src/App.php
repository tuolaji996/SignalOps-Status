<?php
declare(strict_types=1);

namespace SignalOps\Status;

use SignalOps\Status\Auth\Auth;
use SignalOps\Status\Cache\Cache;
use SignalOps\Status\Cache\CacheFactory;
use SignalOps\Status\Config\Config;
use SignalOps\Status\Http\Request;
use SignalOps\Status\I18n\I18n;
use SignalOps\Status\View\Assets;
use SignalOps\Status\View\Navigation;
use SignalOps\Status\View\Preferences;
use SignalOps\Status\View\View;

/** Per-request services handed to controllers. */
final class App
{
    public readonly I18n $i18n;
    public readonly Auth $auth;
    public readonly Navigation $nav;
    public readonly View $view;
    private ?Cache $cache = null;

    /** Set by the kernel once the session is checked. */
    public bool $authenticated = false;
    public string $csrf = '';

    public function __construct(
        public readonly string $root,
        public readonly Config $config,
        public readonly Request $request,
        public readonly Preferences $prefs,
        ?Navigation $nav = null,
        ?Assets $assets = null,
    ) {
        $this->i18n = new I18n($prefs->locale, $root . '/lang', (string)$config->get('app.timezone', ''));
        $this->auth = new Auth($config->all(), $request);
        $this->nav = $nav ?? Navigation::fromDirectory($root . '/src/Nav');
        $this->view = new View($this, $assets ?? Assets::forSite($root));
    }

    public function cache(): Cache
    {
        return $this->cache ??= CacheFactory::create(CacheFactory::defaultDir((string)$this->config->get('ui.cache_dir', '')));
    }

    public function demo(): bool
    {
        return $this->config->demo();
    }

    public function t(string $key, array $params = []): string
    {
        return $this->i18n->t($key, $params);
    }
}
