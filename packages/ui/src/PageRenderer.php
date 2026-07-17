<?php

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
     * @param string[] $scripts Script src paths, in order, appended to <head>.
     */
    public static function render(
        Widget $widgetTree,
        string $path,
        string $appName,
        array $scripts,
        bool $debug,
    ): never {
        if (Navigation::isPartial()) {
            header('Content-Type: application/json');
            echo json_encode([
                'html' => $widgetTree->render(),
                'path' => $path,
                'theme' => $_SESSION['theme'] ?? 'light',
            ]);
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
                {$body}
            </body>

            </html>
            HTML;
        exit;
    }
}
