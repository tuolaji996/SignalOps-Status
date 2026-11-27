<?php
declare(strict_types=1);

namespace SignalOps\Status\View;

use SignalOps\Status\Http\Request;
use SignalOps\Status\I18n\I18n;

/**
 * Language and theme chosen by the visitor.
 *
 * ?lang=en|zh-CN and ?theme=system|light|dark set a cookie (signalops_lang / signalops_theme, one year) and
 * redirect to the same URL without the parameter. The server writes the theme into
 * <html data-theme> so there is no flash; "system" leaves it out and CSS follows
 * prefers-color-scheme.
 */
final class Preferences
{
    public const LANG_COOKIE = 'signalops_lang';
    public const THEME_COOKIE = 'signalops_theme';
    public const THEMES = ['system', 'light', 'dark'];

    public function __construct(public readonly string $locale, public readonly string $theme, public readonly bool $explicitChange = false)
    {
    }

    public static function fromRequest(Request $request): self
    {
        $explicitLang = $request->query('lang') !== '' ? $request->query('lang') : null;
        $locale = I18n::negotiate($explicitLang, $request->cookie(self::LANG_COOKIE), $request->header('Accept-Language'));
        $themeQuery = $request->query('theme');
        $theme = in_array($themeQuery, self::THEMES, true) ? $themeQuery : (string)$request->cookie(self::THEME_COOKIE);
        if (!in_array($theme, self::THEMES, true)) {
            $theme = 'system';
        }
        $explicit = ($explicitLang !== null && I18n::normalize($explicitLang) !== null) || in_array($themeQuery, self::THEMES, true);
        return new self($locale, $theme, $explicit);
    }

    /** @return array<string, mixed> setcookie() options */
    public static function cookieOptions(Request $request): array
    {
        return ['expires' => time() + 31536000, 'path' => '/', 'secure' => $request->isHttps(), 'httponly' => false, 'samesite' => 'Lax'];
    }

    /** Value for <html data-theme>, or null for "follow the system". */
    public function dataTheme(): ?string
    {
        return $this->theme === 'system' ? null : $this->theme;
    }

    public function nextTheme(): string
    {
        return match ($this->theme) {
            'system' => 'light',
            'light' => 'dark',
            default => 'system',
        };
    }

    public function otherLocale(): string
    {
        return $this->locale === 'zh-CN' ? 'en' : 'zh-CN';
    }
}
