<?php

/**
 * Invoked by Engine\Native\AsyncTask::spawn() via proc_open() as a genuinely
 * separate OS process — argv: [taskKey, "HandlerClass::method", jsonArgs].
 * Runs the handler and writes its JSON-encoded result where
 * AsyncTask::poll() will find it on the next render. Never invoked directly
 * by a request; only ever a subprocess of the main PHP server.
 */

require __DIR__ . '/../vendor/autoload.php';

use Engine\Native\AsyncTask;

[, $taskKey, $handlerRef, $argsJson] = $argv;
[$handlerClass, $handlerMethod] = explode('::', $handlerRef, 2);
$args = json_decode($argsJson, true) ?? [];

$resultPath = AsyncTask::resultPathFor($taskKey);
$lockPath = "{$resultPath}.lock";

try {
    $data = $handlerClass::$handlerMethod(...$args);
    file_put_contents($resultPath, json_encode(['status' => 'done', 'data' => $data, 'error' => null]));
} catch (\Throwable $e) {
    file_put_contents($resultPath, json_encode(['status' => 'error', 'data' => null, 'error' => $e->getMessage()]));
} finally {
    @unlink($lockPath);
}
