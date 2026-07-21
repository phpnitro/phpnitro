<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Connectivity;

use Engine\Widget;

/**
 * connectivity_plus equivalent — a live online/offline indicator.
 * assets/js/connectivity.js paints the real state right after mount
 * (navigator.onLine, native ConnectivityManager when available) and
 * repaints on every browser online/offline event, same "server renders a
 * placeholder, JS keeps it live" idiom as StreamBuilder/FutureBuilder.
 * PHP itself cannot know the client's connectivity synchronously (a page
 * request obviously already reached the server), so there is no
 * server-rendered "real" initial state to show — the placeholder is
 * intentionally generic until JS paints over it on the very next frame.
 *
 * window.phpxConnectivity (isOnline()/connectionType()/onChange()) is
 * usable directly by any other script, independent of this widget.
 */
final class ConnectivityBadge extends Widget
{
    public function __construct(
        private readonly string $onlineLabel = 'En ligne',
        private readonly string $offlineLabel = 'Hors ligne',
        private readonly string $onlineClasses = 'text-sm text-green-600 dark:text-green-400',
        private readonly string $offlineClasses = 'text-sm text-red-600 dark:text-red-400',
    ) {
    }

    public static function make(
        string $onlineLabel = 'En ligne',
        string $offlineLabel = 'Hors ligne',
        string $onlineClasses = 'text-sm text-green-600 dark:text-green-400',
        string $offlineClasses = 'text-sm text-red-600 dark:text-red-400',
    ): self {
        return new self($onlineLabel, $offlineLabel, $onlineClasses, $offlineClasses);
    }

    public function render(): string
    {
        return sprintf(
            '<span data-connectivity-badge data-online-label="%s" data-offline-label="%s" '
            . 'data-online-class="%s" data-offline-class="%s" class="%s"></span>',
            htmlspecialchars($this->onlineLabel, ENT_QUOTES),
            htmlspecialchars($this->offlineLabel, ENT_QUOTES),
            htmlspecialchars($this->onlineClasses, ENT_QUOTES),
            htmlspecialchars($this->offlineClasses, ENT_QUOTES),
            htmlspecialchars($this->offlineClasses, ENT_QUOTES),
        );
    }
}
