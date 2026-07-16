<?php

namespace Engine;

/**
 * Key-based i18n helper (server-side, no network needed) — distinct
 * from GoogleTranslate, which machine-translates the rendered page
 * client-side. Use Translator for strings you control the wording of.
 */
final class Translator
{
    /** @var array<string, array<string, string>> */
    private static array $translations = [];

    private static string $locale = 'fr';

    /** @param array<string, string> $translations */
    public static function load(string $locale, array $translations): void
    {
        self::$translations[$locale] = [...(self::$translations[$locale] ?? []), ...$translations];
    }

    public static function setLocale(string $locale): void
    {
        self::$locale = $locale;
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    /** @param array<string, string|int> $params */
    public static function t(string $key, array $params = []): string
    {
        $template = self::$translations[self::$locale][$key] ?? $key;

        foreach ($params as $name => $value) {
            $template = str_replace(":{$name}", (string) $value, $template);
        }

        return $template;
    }
}
