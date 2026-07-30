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
 * Flutter's FutureBuilder, backed by AsyncTask's real background process
 * instead of a Dart Future. On every render this asks AsyncTask::poll()
 * for the current state:
 *
 * - pending: paints $loading (typically a RenderSpinner) and tells the
 *   client to refetch this same screen again shortly (NativeCanvas::
 *   pollAgain()) — no navigation, no user action, just "check again."
 * - done: paints $builder($data).
 * - error: paints a plain error message.
 *
 * $args must be JSON-safe (see AsyncTask) — no closures, no objects
 * without a __toString()/data shape that survives json_encode().
 */
final class RenderAsync implements RenderNode
{
    private RenderNode $active;
    private bool $pending;
    private Size $size;

    /**
     * @param array<int, mixed> $args
     * @param callable(mixed): RenderNode $builder
     */
    public function __construct(
        private readonly string $taskKey,
        string $handlerClass,
        string $handlerMethod,
        array $args,
        RenderNode $loading,
        callable $builder,
        private readonly int $pollIntervalMs = 400,
    ) {
        $result = AsyncTask::poll($taskKey, $handlerClass, $handlerMethod, $args);
        $this->pending = $result['status'] === 'pending';
        $this->active = match ($result['status']) {
            'done' => $builder($result['data']),
            'error' => new RenderText("Erreur : {$result['error']}", Tokens::TEXT_BODY_SMALL, Tokens::danger()->toHex()),
            default => $loading,
        };
        $this->size = Size::zero();
    }

    public function layout(Constraints $constraints): Size
    {
        $this->size = $this->active->layout($constraints);

        return $this->size;
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        if ($this->pending) {
            $canvas->pollAgain($this->pollIntervalMs);
        }
        $this->active->paint($canvas, $x, $y);
    }
}
