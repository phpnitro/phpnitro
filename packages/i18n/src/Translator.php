<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\I18n;

/**
 * Same "one static, overwritten unconditionally at the top of every
 * request before any layout()/paint() call runs" pattern as MediaQuery/
 * Tokens — init() is called once from public/index.php, reading the
 * device's real system locale (NativeRenderPocActivity.kt sends
 * Configuration.locales[0].language the same way it already sends
 * ?dark= for Tokens::init()), so a screen never has to know or care
 * which locale it's rendering for beyond calling t().
 *
 * Translation files are plain PHP arrays (lib/lang/{locale}.php,
 * `return ['key' => 'value', ...];`) — no gettext/.po toolchain, no
 * ICU MessageFormat parser, consistent with every other "pure PHP, no
 * assumption about what's compiled into the on-device PHP binary"
 * package in this framework (Engine\Format\Format's own docblock is the
 * canonical explanation of why). A missing key falls back to the key
 * itself — the same "visibly wrong, not silently blank" convention
 * Symfony/Laravel's translators use, so a missing translation is easy
 * to spot in the running app instead of just rendering empty text.
 */
final class Translator
{
    private static string $locale = 'fr';

    /** @var array<string, string> */
    private static array $translations = [];

    public static function init(string $locale, string $langDir): void
    {
        self::$locale = $locale;
        self::$translations = [];

        $file = rtrim($langDir, '/') . "/{$locale}.php";
        if (is_file($file)) {
            /** @var array<string, string> $loaded */
            $loaded = require $file;
            self::$translations = $loaded;
        }
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    /**
     * @param array<string, string|int|float> $params Simple {name}
     *   interpolation — no plural rules, no gender, no ICU
     *   MessageFormat. Enough for "Bonjour {name}", not enough for "You
     *   have {count} new {count, plural, one{message} other{messages}}"
     *   — a project that genuinely needs plural rules should reach for
     *   a real ICU-backed solution instead of stretching this one.
     */
    public static function t(string $key, array $params = []): string
    {
        $value = self::$translations[$key] ?? $key;
        foreach ($params as $name => $param) {
            $value = str_replace("{{$name}}", (string) $param, $value);
        }

        return $value;
    }

    /** True only when the key resolved to a real translation, not the raw-key fallback — useful for a dev-mode "missing translation" indicator, not needed for normal rendering. */
    public static function has(string $key): bool
    {
        return isset(self::$translations[$key]);
    }
}
