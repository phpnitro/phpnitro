<?php

namespace Engine;

/**
 * Detects requests coming from nav.js's fetch-based interception of link
 * clicks and form submits, as opposed to a real browser navigation (or a
 * plain HTML form submit with JS disabled/unavailable) — nav.js sets this
 * header on every request it makes so the front controller knows whether
 * to answer with a full HTML document or just the rendered widget tree.
 */
final class Navigation
{
    public static function isPartial(): bool
    {
        return ($_SERVER['HTTP_X_PHPX_PARTIAL'] ?? '') === '1';
    }
}
