<?php
declare(strict_types=1);

// MONITOR group (the only area in the open-source build). Data only; see src/View/Navigation.php.
return [
    'order' => 10,
    'label' => 'home.nav.group',
    'items' => [
        ['key' => 'home.overview', 'label' => 'home.nav.overview', 'href' => '/overview'],
        ['key' => 'home.servers', 'label' => 'home.nav.servers', 'href' => '/servers'],
        ['key' => 'home.latency', 'label' => 'home.nav.latency', 'href' => '/latency'],
        ['key' => 'home.logs', 'label' => 'home.nav.logs', 'href' => '/logs'],
        ['key' => 'home.events', 'label' => 'home.nav.events', 'href' => '/events'],
        ['key' => 'home.alerts', 'label' => 'home.nav.alerts', 'href' => '/alerts', 'private' => true, 'new' => true],
    ],
    'mobile_tabs' => [
        ['key' => 'home.overview', 'label' => 'home.nav.overview', 'href' => '/overview', 'order' => 10],
        ['key' => 'home.servers', 'label' => 'home.nav.servers', 'href' => '/servers', 'order' => 20],
        ['key' => 'home.alerts', 'label' => 'home.nav.alerts', 'href' => '/alerts', 'order' => 30],
    ],
];
