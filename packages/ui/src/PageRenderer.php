<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine;

/**
 * Emits either a full HTML document, or — for nav.js-intercepted requests —
 * a small JSON envelope carrying just the rendered widget tree. Same "PHP
 * renders, JS only swaps" split StreamBuilder/FutureBuilder already use for
 * their own fragments, just wired to normal navigation/actions instead of
 * polling, which is what lets normal link clicks and form submits update
 * the page without a full reload.
 *
 * The <title> and CSRF token are never sent in the partial payload: title
 * is currently invariant across screens (just APP_NAME), and the CSRF token
 * is session-stable (Csrf::token() never rotates), so nothing needs
 * refreshing client-side after a swap.
 */
final class PageRenderer
{
    /**
     * A redirect target can be an external URL (e.g. a hosted Stripe
     * Checkout session) — that can't be resolved through the local Router
     * or rendered as a fragment. In partial mode, tell nav.js to do a real
     * top-level navigation there instead of trying to swap it in.
     */
    public static function redirectExternally(string $url): never
    {
        header('Content-Type: application/json');
        echo json_encode(['redirect' => $url]);
        exit;
    }

    public static function isExternalUrl(string $target): bool
    {
        return str_starts_with($target, 'http://') || str_starts_with($target, 'https://');
    }

    /**
     * $persistentNav is rendered exactly once per HTTP request and lives
     * OUTSIDE #phpx-content, the region nav.js ever swaps — it is never
     * part of $widgetTree, and never appears in the partial JSON's "html"
     * either. Only its visibility changes per route, via the
     * "showBottomNav" flag (both here and in the partial payload) — nav.js
     * toggles a `hidden` class instead of ever destroying/recreating the
     * nav bar, which is what causes the jump a full node replacement would.
     *
     * @param string[] $scripts Script src paths, in order, appended to <head>.
     */
    public static function render(
        Widget $widgetTree,
        string $path,
        string $appName,
        array $scripts,
        bool $debug,
        ?Widget $persistentNav = null,
        bool $showBottomNav = true,
        ?Screen $screen = null,
    ): never {
        $renderStartedAt = microtime(true);

        if (Navigation::isPartial()) {
            $html = $widgetTree->render();
            $theme = $_SESSION['theme'] ?? 'light';

            $payload = [
                'html' => $html,
                'path' => $path,
                'theme' => $theme,
                'showBottomNav' => $showBottomNav,
            ];

            if ($debug) {
                $payload['devtools'] = self::devToolsData($path, $theme, $renderStartedAt, $screen);
            }

            header('Content-Type: application/json');
            echo json_encode($payload);
            exit;
        }

        $theme = $_SESSION['theme'] ?? 'light';
        $themeClass = $theme === 'dark' ? 'dark' : '';
        $csrfToken = htmlspecialchars(Csrf::token(), ENT_QUOTES);
        $title = htmlspecialchars($appName, ENT_QUOTES);
        $scriptTags = implode("\n    ", array_map(
            static fn (string $src) => '<script src="' . htmlspecialchars($src, ENT_QUOTES) . '" defer></script>',
            $scripts,
        ));
        $devReloadTag = $debug ? '<script src="/assets/js/dev-reload.js" defer></script>' : '';
        $body = $widgetTree->render();
        $devToolsHtml = $debug ? self::devToolsPanel(self::devToolsData($path, $theme, $renderStartedAt, $screen)) : '';
        $navWrapperClass = $showBottomNav ? '' : 'hidden';
        $navHtml = $persistentNav !== null
            ? "<div id=\"phpx-bottom-nav-wrapper\" class=\"{$navWrapperClass}\">{$persistentNav->render()}</div>"
            : '';

        echo <<<HTML
            <!doctype html>
            <html lang="fr" class="{$themeClass}">

            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="csrf-token" content="{$csrfToken}">
                <title>{$title}</title>
                <link rel="stylesheet" href="/tailwind.css">
                {$scriptTags}
                {$devReloadTag}
            </head>

            <body class="bg-gray-50 dark:bg-gray-900 dark:text-gray-100 min-h-screen">
                <div id="phpx-content">{$body}</div>
                {$navHtml}
                {$devToolsHtml}
            </body>

            </html>
            HTML;
        exit;
    }

    /**
     * Shared by both the full-page and partial (nav.js) response paths —
     * a nav.js swap now carries this same payload under "devtools" so the
     * panel updates on every route/theme change, not just a full reload.
     * $screen->state() exposes the current screen's session state;
     * Preferences::all() is included only when that optional package is
     * actually installed (packages/ui has no hard dependency on it).
     *
     * @return array<string, mixed>
     */
    private static function devToolsData(string $path, string $theme, float $renderStartedAt, ?Screen $screen): array
    {
        $preferences = class_exists(\Engine\Preferences\Preferences::class)
            ? \Engine\Preferences\Preferences::all()
            : null;

        return [
            'path' => $path,
            'theme' => $theme,
            'renderMs' => round((microtime(true) - $renderStartedAt) * 1000, 2),
            'memoryKb' => round(memory_get_peak_usage(true) / 1024),
            'phpVersion' => PHP_VERSION,
            'state' => $screen?->state() ?? [],
            'preferences' => $preferences,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function devToolsPanel(array $data): string
    {
        $encoded = htmlspecialchars(json_encode($data, JSON_THROW_ON_ERROR), ENT_QUOTES);

        return "<div id=\"phpx-devtools-root\" data-phpx-devtools=\"{$encoded}\"></div>"
            . '<script src="/assets/js/devtools.js" defer></script>';
    }
}
