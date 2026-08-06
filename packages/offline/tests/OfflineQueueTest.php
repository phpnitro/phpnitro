<?php

namespace Engine\Offline\Tests;

use Engine\Database\Database;
use Engine\Offline\OfflineQueue;
use PHPUnit\Framework\TestCase;

final class OfflineQueueTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/phpnitro-offline-test-' . uniqid() . '.sqlite';
        Database::useSqlitePath($this->path);

        $reflection = new \ReflectionClass(OfflineQueue::class);
        $reflection->getProperty('schemaEnsured')->setValue(null, false);
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(Database::class);
        $reflection->getProperty('connection')->setValue(null, null);
        $reflection->getProperty('sqlitePath')->setValue(null, null);

        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testStartsEmpty(): void
    {
        $this->assertSame(0, OfflineQueue::count());
        $this->assertSame([], OfflineQueue::pending());
    }

    public function testEnqueuePersistsActionAndPayload(): void
    {
        OfflineQueue::enqueue('create_order', ['product_id' => 42, 'quantity' => 2]);

        $pending = OfflineQueue::pending();
        $this->assertCount(1, $pending);
        $this->assertSame('create_order', $pending[0]['action']);
        $this->assertSame(['product_id' => 42, 'quantity' => 2], $pending[0]['payload']);
        $this->assertSame(1, OfflineQueue::count());
    }

    public function testFlushSendsInOrderAndRemovesSucceeded(): void
    {
        OfflineQueue::enqueue('first', ['n' => 1]);
        OfflineQueue::enqueue('second', ['n' => 2]);

        $sentOrder = [];
        $result = OfflineQueue::flush(function (string $action, array $payload) use (&$sentOrder): bool {
            $sentOrder[] = $action;

            return true;
        });

        $this->assertSame(['first', 'second'], $sentOrder);
        $this->assertSame(['sent' => 2, 'remaining' => 0], $result);
        $this->assertSame(0, OfflineQueue::count());
    }

    public function testFlushStopsAtFirstFailureAndKeepsItQueued(): void
    {
        OfflineQueue::enqueue('first', []);
        OfflineQueue::enqueue('second', []);
        OfflineQueue::enqueue('third', []);

        $attempts = [];
        $result = OfflineQueue::flush(function (string $action) use (&$attempts): bool {
            $attempts[] = $action;

            return $action !== 'second';
        });

        $this->assertSame(['first', 'second'], $attempts);
        $this->assertSame(['sent' => 1, 'remaining' => 2], $result);

        $remaining = array_column(OfflineQueue::pending(), 'action');
        $this->assertSame(['second', 'third'], $remaining);
    }

    public function testClearRemovesEverything(): void
    {
        OfflineQueue::enqueue('a', []);
        OfflineQueue::enqueue('b', []);
        OfflineQueue::clear();

        $this->assertSame(0, OfflineQueue::count());
    }
}
