<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Diagnostics;

/**
 * firebase_crashlytics/Sentry equivalent, self-hosted: no external account
 * needed (none available in this environment). PHP errors are captured via
 * set_exception_handler()/set_error_handler() and JS errors via
 * window.onerror (see assets/js/diagnostics.js), both POSTing to a single
 * app-provided endpoint instead of a third-party SaaS — swap
 * CrashReporter::install()'s $reportUrl for a real Sentry/Crashlytics-
 * compatible endpoint later without changing call sites.
 */
final class CrashReporter
{
    private static ?string $reportUrl = null;

    public static function install(string $reportUrl): void
    {
        self::$reportUrl = $reportUrl;

        set_exception_handler(static function (\Throwable $e): void {
            CrashReporter::report($e::class, $e->getMessage(), $e->getFile() . ':' . $e->getLine(), $e->getTraceAsString());
        });

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            CrashReporter::report('PHP Error', $message, "{$file}:{$line}", '');

            return false;
        });
    }

    public static function report(string $type, string $message, string $location, string $trace): void
    {
        if (self::$reportUrl === null) {
            return;
        }

        $payload = json_encode([
            'type' => $type,
            'message' => $message,
            'location' => $location,
            'trace' => $trace,
            'at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ], JSON_THROW_ON_ERROR);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 2,
                'ignore_errors' => true,
            ],
        ]);

        // Best-effort, fire-and-forget: a crash reporter that itself throws
        // (network down, endpoint unreachable) must never mask the
        // original error or crash the request a second time.
        try {
            @file_get_contents(self::$reportUrl, false, $context);
        } catch (\Throwable) {
        }
    }
}
