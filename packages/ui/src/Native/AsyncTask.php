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
 * The closest thing this stack has to a Dart isolate: a genuinely
 * separate OS process (proc_open(), confirmed to work on the cross-
 * compiled Android PHP build via a throwaway diagnostic route — a plain
 * `sleep 2 &&` background command returned control to the request in
 * ~1ms, not 2000ms, and its result file only existed after the fact),
 * with no shared memory with the request that queued it. There's no
 * closure-serialization trick here — the work is a plain class::method
 * reference plus JSON-safe args (the same "point at a named handler,
 * don't try to ship a live closure" shape a queue job or a cron entry
 * uses), run by public/async-runner.php as its own process.
 *
 * poll() is called every render of whatever screen wants the result —
 * see Async, which is the actual widget-level API. Three states:
 * no result file yet and nothing running -> spawn it, report pending;
 * a lock file but no result yet -> still pending, don't spawn twice;
 * a result file -> done (or error), read and return it.
 */
final class AsyncTask
{
    private static function cacheDir(): string
    {
        $dir = sys_get_temp_dir() . '/phpnitro_async';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    public static function resultPathFor(string $taskKey): string
    {
        return self::cacheDir() . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $taskKey) . '.json';
    }

    private static function lockPathFor(string $taskKey): string
    {
        return self::resultPathFor($taskKey) . '.lock';
    }

    /**
     * @param array<int, mixed> $args JSON-safe positional arguments — passed to the
     *                                background process as a JSON string, so nothing
     *                                that can't survive json_encode()/json_decode() works.
     * @return array{status: 'pending'|'done'|'error', data: mixed, error: ?string}
     */
    public static function poll(string $taskKey, string $handlerClass, string $handlerMethod, array $args = []): array
    {
        $resultPath = self::resultPathFor($taskKey);
        if (is_file($resultPath)) {
            $result = json_decode((string) file_get_contents($resultPath), true);

            return $result ?? ['status' => 'error', 'data' => null, 'error' => 'corrupt result file'];
        }

        if (is_file(self::lockPathFor($taskKey))) {
            return ['status' => 'pending', 'data' => null, 'error' => null];
        }

        touch(self::lockPathFor($taskKey));
        self::spawn($taskKey, $handlerClass, $handlerMethod, $args);

        return ['status' => 'pending', 'data' => null, 'error' => null];
    }

    /** Clears a task's cached result/lock so the next poll() runs it again from scratch. */
    public static function reset(string $taskKey): void
    {
        @unlink(self::resultPathFor($taskKey));
        @unlink(self::lockPathFor($taskKey));
    }

    /**
     * @param array<int, mixed> $args
     */
    private static function spawn(string $taskKey, string $handlerClass, string $handlerMethod, array $args): void
    {
        $runner = dirname(__DIR__, 4) . '/public/async-runner.php';
        $phpBinary = PHP_BINARY !== '' ? PHP_BINARY : 'php';

        $command = sprintf(
            '%s %s %s %s %s > /dev/null 2>&1 &',
            escapeshellarg($phpBinary),
            escapeshellarg($runner),
            escapeshellarg($taskKey),
            escapeshellarg("{$handlerClass}::{$handlerMethod}"),
            escapeshellarg(json_encode($args)),
        );

        $descriptors = [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']];
        $process = @proc_open($command, $descriptors, $pipes);
        if (is_resource($process)) {
            fclose($pipes[0]);
            // Deliberately no proc_close($process) — that blocks until
            // the child exits, exactly what "fire and forget" must not do.
        }
    }
}
