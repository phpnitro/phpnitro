<?php

namespace Engine\Retry\Tests;

use Engine\Retry\Retry;
use PHPUnit\Framework\TestCase;

final class RetryTest extends TestCase
{
    public function testReturnsResultOnFirstSuccess(): void
    {
        $this->assertSame('ok', Retry::run(static fn () => 'ok'));
    }

    public function testRetriesUntilSuccess(): void
    {
        $attempts = 0;
        $result = Retry::run(static function () use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new \RuntimeException('transient failure');
            }

            return 'recovered';
        }, times: 5, delayMs: 1);

        $this->assertSame('recovered', $result);
        $this->assertSame(3, $attempts);
    }

    public function testRethrowsAfterExhaustingAttempts(): void
    {
        $attempts = 0;
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('always fails');

        try {
            Retry::run(static function () use (&$attempts) {
                $attempts++;
                throw new \RuntimeException('always fails');
            }, times: 3, delayMs: 1);
        } finally {
            $this->assertSame(3, $attempts);
        }
    }
}
