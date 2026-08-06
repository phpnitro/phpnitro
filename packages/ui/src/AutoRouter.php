<?php

namespace Engine;

/**
 * Discovers routable classes straight from a directory's own PSR-4 files —
 * no hand-edited route table. A file `lib/pages/AboutPage.php` becomes
 * reachable at ?screen=about the moment it exists, the same way Next.js's
 * pages/ or Flutter's generated go_router routes work off file presence and
 * naming convention instead of a registry a codegen step has to keep in
 * sync. `phpx make:page`/`make:entity` only need to create the file; there
 * is nothing left to wire up.
 */
final class AutoRouter
{
    /**
     * @param string[] $stripSuffixes Class-name suffixes tried in order;
     *   the first match is removed before kebab-casing (e.g. "AboutPage"
     *   with ["Page"] -> "about"). A class left with an empty base after
     *   stripping (e.g. "HomePage" alone) maps to the 'home' key.
     * @param string $requiredMethod Only files whose class actually
     *   declares this method are registered — a stray non-page/controller
     *   class dropped in the same directory is silently skipped rather
     *   than crashing dispatch.
     * @return array<string, class-string> route key -> fully-qualified class
     */
    public static function discover(
        string $directory,
        string $namespace,
        array $stripSuffixes,
        string $requiredMethod,
    ): array {
        $map = [];

        foreach (glob(rtrim($directory, '/') . '/*.php') ?: [] as $file) {
            $className = basename($file, '.php');
            $fqcn = rtrim($namespace, '\\') . '\\' . $className;

            if (!class_exists($fqcn) || !method_exists($fqcn, $requiredMethod)) {
                continue;
            }

            $map[self::routeKey($className, $stripSuffixes)] = $fqcn;
        }

        return $map;
    }

    /**
     * @param string[] $stripSuffixes
     */
    public static function routeKey(string $className, array $stripSuffixes): string
    {
        $base = $className;
        foreach ($stripSuffixes as $suffix) {
            if (str_ends_with($base, $suffix)) {
                $base = substr($base, 0, -strlen($suffix));
                break;
            }
        }

        $kebab = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $base));

        return $kebab === '' ? 'home' : $kebab;
    }
}
