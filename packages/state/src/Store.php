<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\State;

/**
 * A typed, namespaced API over $_SESSION for state that needs to survive
 * across screens within one login session but doesn't belong in a real
 * database table (a wizard's in-progress answers, a filter the user picked
 * on one screen and expects to still be set on another, a draft). This is
 * this framework's answer to "where do I put shared state" without
 * reaching for a Provider/Bloc-style dependency-injected store — there is
 * no persistent object graph to inject INTO, since every request is a
 * fresh PHP process (see docs/architecture.md's "Le cycle" section); the
 * only thing that actually survives between requests is $_SESSION itself,
 * so this is a thin, safer API over that, not a different mechanism.
 *
 * Deliberately NOT the same thing as Engine\Preferences\Preferences,
 * which is SQLite-backed and survives across sessions/app reinstalls (a
 * user setting). Store's own data disappears with the session — reach for
 * Preferences instead the moment something needs to outlive "the user is
 * currently logged in".
 *
 * Every key is namespaced under "store." in $_SESSION so this can't
 * collide with the ad hoc $_SESSION keys the rest of the framework
 * already uses directly (auth_user, oauth_state, widgets_last_refresh...).
 */
final class Store
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[self::namespaced($key)] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[self::namespaced($key)] = $value;
    }

    public static function has(string $key): bool
    {
        return array_key_exists(self::namespaced($key), $_SESSION);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[self::namespaced($key)]);
    }

    /**
     * Read-modify-write in one call — the exact "increment a counter",
     * "toggle a flag", "append to a list" boilerplate every screen that
     * touched $_SESSION/Preferences directly used to hand-roll (see
     * NativeHomeScreen's own counter before this existed: get, cast,
     * add one, set, four lines for one idea). $updater receives the
     * current value (or $default if unset) and returns the new one.
     */
    public static function update(string $key, callable $updater, mixed $default = null): mixed
    {
        $value = $updater(self::get($key, $default));
        self::set($key, $value);

        return $value;
    }

    /** Clears every key this class has ever set — not the whole $_SESSION, just this namespace. */
    public static function clear(): void
    {
        foreach (array_keys($_SESSION) as $key) {
            if (str_starts_with((string) $key, 'store.')) {
                unset($_SESSION[$key]);
            }
        }
    }

    private static function namespaced(string $key): string
    {
        return "store.{$key}";
    }
}
