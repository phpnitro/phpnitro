<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Native;

/**
 * A declarative alternative to growing public/index.php's own
 * `match ($screen) { ... }` block one more arm at a time — register()
 * a screen name against a closure that builds its Widget tree, and
 * public/index.php checks has()/build() before falling through to the
 * legacy match(). Both can coexist indefinitely: this was introduced to
 * give NEW screens a cleaner place to register from (see 'product' in
 * public/index.php for the real, wired example) without forcing a risky
 * one-shot migration of the ~40 screens already dispatched the old way.
 *
 * No route-pattern/placeholder syntax ("product/:id") on purpose — this
 * framework's routes already carry their params as a real query string
 * (see docs/architecture.md's "Routes à paramètres" section,
 * navigate:product?id=42&tab=reviews), so $_GET is already exactly
 * what a screen needs; a builder closure just reads it directly the same
 * way every match() arm already does, no new param-extraction layer to
 * learn.
 *
 * Registration is expected on EVERY request (call register() unconditionally
 * near the top of public/index.php, not just once) — this is a plain
 * static array, reset to empty at the start of every fresh PHP process,
 * but php -S reuses one process across requests in dev, so nothing
 * populates $routes again on request #2 unless the registration call
 * itself runs again every time. Harmless either way: re-registering the
 * same screen name just overwrites its closure with an identical one.
 */
final class Router
{
    /** @var array<string, callable(): Widget> */
    private static array $routes = [];

    /** @param callable(): Widget $builder */
    public static function register(string $screen, callable $builder): void
    {
        self::$routes[$screen] = $builder;
    }

    public static function has(string $screen): bool
    {
        return isset(self::$routes[$screen]);
    }

    public static function build(string $screen): ?Widget
    {
        $builder = self::$routes[$screen] ?? null;

        return $builder !== null ? $builder() : null;
    }

    /** Test-only — clears every registration so one test's routes can't leak into the next. */
    public static function reset(): void
    {
        self::$routes = [];
    }
}
