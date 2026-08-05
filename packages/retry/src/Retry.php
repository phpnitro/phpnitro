<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Retry;

/**
 * Retries a callback with exponential backoff — the pattern every HTTP
 * call to a flaky third-party API (payments, geocoding, translation...)
 * eventually needs, hand-rolled slightly differently at every call site
 * without this. Re-throws the last exception once $times is exhausted,
 * never swallows a permanent failure silently.
 */
final class Retry
{
    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function run(callable $callback, int $times = 3, int $delayMs = 200, float $backoffMultiplier = 2.0): mixed
    {
        $attempt = 0;
        $delay = $delayMs;

        while (true) {
            try {
                return $callback();
            } catch (\Throwable $e) {
                $attempt++;
                if ($attempt >= $times) {
                    throw $e;
                }
                usleep($delay * 1000);
                $delay = (int) ($delay * $backoffMultiplier);
            }
        }
    }
}
