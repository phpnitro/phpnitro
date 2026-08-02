<?php

namespace Engine\App;

/**
 * Async's demo handler — a deliberately slow computation (a real
 * sleep(), not a mocked delay) run by public/async-runner.php as its own
 * OS process. Must stay a plain static method with JSON-safe args/return:
 * AsyncTask ships the args as a JSON string and the runner calls this
 * exactly like a queue job, not a live closure.
 */
final class AsyncDemoHandler
{
    /** @return array{message: string, computedAt: string} */
    public static function fetchSlowData(int $delaySeconds): array
    {
        sleep($delaySeconds);

        return [
            'message' => 'Données calculées dans un vrai processus séparé (proc_open), pas dans la requête HTTP.',
            'computedAt' => date('H:i:s'),
        ];
    }
}
