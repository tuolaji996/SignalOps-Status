<?php
declare(strict_types=1);

namespace SignalOps\Status\View;

/**
 * Sidebar groups and phone tab bar. Each area owns one data file, src/Nav/<area>.php (data only; no classes in src/Nav or src/Routes):
 *
 *   return [
 *       'order' => 10,                       // group position in the sidebar
 *       'label' => 'home.nav.group',          // lang key of the group heading (MONITOR)
 *       'items' => [
 *           ['key' => 'home.alerts', 'label' => 'home.nav.alerts', 'href' => '/alerts',
 *            'private' => true, 'new' => true],
 *       ],
 *       'mobile_tabs' => [                     // optional: tabs in the phone bottom bar
 *           ['key' => 'home.overview', 'label' => 'home.nav.overview', 'href' => '/overview', 'order' => 10],
 *       ],
 *   ];
 *
 * 'key' is what a route's 'nav' points at to mark the item active.
 */
final class Navigation
{
    /** @param list<array<string, mixed>> $groups */
    public function __construct(private readonly array $groups)
    {
    }

    public static function fromDirectory(string $dir): self
    {
        $groups = [];
        $files = glob(rtrim($dir, '/') . '/*.php') ?: [];
        sort($files);
        foreach ($files as $file) {
            $group = (static fn (string $__file): mixed => require $__file)($file);
            if (!is_array($group) || !isset($group['items']) || !is_array($group['items'])) {
                continue;
            }
            $group['area'] = basename($file, '.php');
            $groups[] = $group;
        }
        usort($groups, static fn (array $a, array $b): int => ((int)($a['order'] ?? 0)) <=> ((int)($b['order'] ?? 0)));
        return new self($groups);
    }

    /** @return list<array<string, mixed>> */
    public function groups(): array
    {
        return $this->groups;
    }

    /** Phone bottom bar tabs, sorted by 'order'. The "More" tab is added by the layout. */
    public function mobileTabs(): array
    {
        $tabs = [];
        foreach ($this->groups as $group) {
            foreach ((array)($group['mobile_tabs'] ?? []) as $tab) {
                $tab['area'] = $group['area'];
                $tabs[] = $tab;
            }
        }
        usort($tabs, static fn (array $a, array $b): int => ((int)($a['order'] ?? 0)) <=> ((int)($b['order'] ?? 0)));
        return $tabs;
    }

    /** @return array<string, mixed>|null */
    public function item(string $key): ?array
    {
        foreach ($this->groups as $group) {
            foreach ($group['items'] as $item) {
                if (($item['key'] ?? '') === $key) {
                    return $item + ['area' => $group['area']];
                }
            }
        }
        return null;
    }
}
