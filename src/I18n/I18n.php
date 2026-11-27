<?php
declare(strict_types=1);

namespace SignalOps\Status\I18n;

/**
 * Interface text, numbers, dates and relative times in en / zh-CN.
 *
 * Strings live in lang/<locale>/<area>.php, each returning a flat ['key' => 'text'] array.
 * A key is looked up as "<area>.<key>", e.g. lang/en/home.php ['alerts.title' => 'Alerts'] is
 * t('home.alerts.title'). Placeholders use {name}: t('common.time.min_ago', ['n' => 5]).
 * Data (hostnames, services, tickers, AI output, event text) is never translated.
 */
final class I18n
{
    public const LOCALES = ['en', 'zh-CN'];
    public const DEFAULT_LOCALE = 'en';
    /** Default display timezone; the app passes config app.timezone instead. */
    public const TIMEZONE = 'America/Toronto';

    public readonly string $timezone;

    /** @var array<string, string> */
    private array $strings = [];

    /** @var array<string, string> */
    private array $fallback = [];

    public function __construct(public readonly string $locale, private readonly string $langDir, ?string $timezone = null)
    {
        $timezone = trim((string)$timezone);
        $this->timezone = $timezone !== '' && in_array($timezone, \DateTimeZone::listIdentifiers(), true) ? $timezone : self::TIMEZONE;
        $this->strings = self::loadLocale($langDir, $locale);
        $this->fallback = $locale === self::DEFAULT_LOCALE ? $this->strings : self::loadLocale($langDir, self::DEFAULT_LOCALE);
    }

    /** @return array<string, string> "<area>.<key>" => text */
    public static function loadLocale(string $langDir, string $locale): array
    {
        $out = [];
        $files = glob(rtrim($langDir, '/') . '/' . $locale . '/*.php') ?: [];
        sort($files);
        foreach ($files as $file) {
            $area = basename($file, '.php');
            $data = (static fn (string $__file): mixed => require $__file)($file);
            if (!is_array($data)) {
                continue;
            }
            foreach ($data as $key => $text) {
                $out[$area . '.' . $key] = (string)$text;
            }
        }
        return $out;
    }

    /**
     * Pick the locale: explicit choice (?lang= or switcher) -> cookie -> Accept-Language -> en.
     * Any zh* (zh, zh-CN, zh-Hans, zh-TW...) maps to zh-CN, the only Chinese variant we ship.
     */
    public static function negotiate(?string $explicit, ?string $cookie, string $acceptLanguage): string
    {
        foreach ([$explicit, $cookie] as $candidate) {
            $normalized = self::normalize($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }
        $ranked = [];
        foreach (explode(',', $acceptLanguage) as $i => $part) {
            $pieces = explode(';', trim($part));
            $tag = trim($pieces[0]);
            $q = 1.0;
            foreach (array_slice($pieces, 1) as $param) {
                if (preg_match('/^\s*q=([0-9.]+)\s*$/', $param, $m)) {
                    $q = (float)$m[1];
                }
            }
            if ($tag !== '' && $q > 0) {
                $ranked[] = [$q, -$i, $tag];
            }
        }
        rsort($ranked);
        foreach ($ranked as [, , $tag]) {
            $normalized = self::normalize($tag);
            if ($normalized !== null) {
                return $normalized;
            }
        }
        return self::DEFAULT_LOCALE;
    }

    public static function normalize(?string $tag): ?string
    {
        if ($tag === null) {
            return null;
        }
        $tag = strtolower(trim($tag));
        if ($tag === 'en' || str_starts_with($tag, 'en-')) {
            return 'en';
        }
        if ($tag === 'zh' || str_starts_with($tag, 'zh-') || str_starts_with($tag, 'zh_')) {
            return 'zh-CN';
        }
        return null;
    }

    /** @param array<string, string|int|float> $params */
    public function t(string $key, array $params = []): string
    {
        $text = $this->strings[$key] ?? $this->fallback[$key] ?? $key;
        if ($params === []) {
            return $text;
        }
        $replace = [];
        foreach ($params as $name => $value) {
            $replace['{' . $name . '}'] = (string)$value;
        }
        return strtr($text, $replace);
    }

    public function has(string $key): bool
    {
        return isset($this->strings[$key]);
    }

    /** Short label for the configured timezone: "America/Toronto" -> "Toronto". */
    public function zoneLabel(): string
    {
        $parts = explode('/', $this->timezone);
        return str_replace('_', ' ', (string)end($parts));
    }

    /** HTML lang attribute value. */
    public function htmlLang(): string
    {
        return $this->locale === 'zh-CN' ? 'zh-CN' : 'en';
    }

    /** Pick the right language from a {"en": ..., "zh-CN": ...} data object (alert titles etc.). */
    public function pick(mixed $localized): string
    {
        if (is_string($localized)) {
            return $localized;
        }
        if (!is_array($localized)) {
            return '';
        }
        foreach ([$this->locale, self::DEFAULT_LOCALE] as $locale) {
            if (isset($localized[$locale]) && is_string($localized[$locale]) && $localized[$locale] !== '') {
                return $localized[$locale];
            }
        }
        foreach ($localized as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }
        return '';
    }

    public function number(int|float $value, int $decimals = 0): string
    {
        return number_format((float)$value, $decimals, '.', ',');
    }

    public function percent(int|float $value, int $decimals = 1): string
    {
        return number_format((float)$value, $decimals, '.', ',') . '%';
    }

    public static function timestamp(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $ts = strtotime($value);
        return $ts === false ? null : $ts;
    }

    /** Wall-clock time in the configured timezone. Formats: 'datetime' (2026-11-13 20:37 EST), 'full' (+seconds), 'date', 'time', 'clock' (20:37:55 EST), 'short' (11-13 20:37). */
    public function datetime(mixed $value, string $format = 'datetime'): string
    {
        $ts = self::timestamp($value);
        if ($ts === null) {
            return $this->t('common.value.none');
        }
        $pattern = match ($format) {
            'full' => 'Y-m-d H:i:s T',
            'date' => 'Y-m-d',
            'time' => 'H:i T',
            'clock' => 'H:i:s T',
            'short' => 'm-d H:i',
            default => 'Y-m-d H:i T',
        };
        return (new \DateTimeImmutable('@' . $ts))->setTimezone(new \DateTimeZone($this->timezone))->format($pattern);
    }

    /** "5 min ago" / "5 分钟前". Future times read "in 5 min". */
    public function relative(mixed $value, ?int $now = null): string
    {
        $ts = self::timestamp($value);
        if ($ts === null) {
            return $this->t('common.value.none');
        }
        $delta = ($now ?? time()) - $ts;
        $future = $delta < 0;
        $delta = abs($delta);
        if ($delta < 45) {
            return $this->t('common.time.just_now');
        }
        [$unit, $n] = match (true) {
            $delta < 3600 => ['min', max(1, intdiv($delta, 60))],
            $delta < 86400 => ['hour', intdiv($delta, 3600)],
            default => ['day', intdiv($delta, 86400)],
        };
        return $this->t('common.time.' . $unit . ($future ? '_in' : '_ago'), ['n' => $n]);
    }

    /** Compact duration: "3 min", "2 h 5 min", "4 d 3 h". */
    public function duration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        if ($seconds < 60) {
            return $this->t('common.duration.sec', ['n' => $seconds]);
        }
        if ($seconds < 3600) {
            return $this->t('common.duration.min', ['n' => intdiv($seconds, 60)]);
        }
        if ($seconds < 86400) {
            return $this->t('common.duration.hour_min', ['h' => intdiv($seconds, 3600), 'm' => intdiv($seconds % 3600, 60)]);
        }
        return $this->t('common.duration.day_hour', ['d' => intdiv($seconds, 86400), 'h' => intdiv($seconds % 86400, 3600)]);
    }
}
