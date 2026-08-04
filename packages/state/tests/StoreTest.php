<?php

namespace Engine\State\Tests;

use Engine\State\Store;
use PHPUnit\Framework\TestCase;

final class StoreTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testGetDefaultWhenUnset(): void
    {
        $this->assertNull(Store::get('missing'));
        $this->assertSame('fallback', Store::get('missing', 'fallback'));
    }

    public function testSetAndGet(): void
    {
        Store::set('draft.title', 'Hello');

        $this->assertSame('Hello', Store::get('draft.title'));
    }

    public function testHas(): void
    {
        $this->assertFalse(Store::has('flag'));
        Store::set('flag', false);
        $this->assertTrue(Store::has('flag'));
    }

    public function testRemove(): void
    {
        Store::set('flag', true);
        Store::remove('flag');

        $this->assertFalse(Store::has('flag'));
        $this->assertNull(Store::get('flag'));
    }

    public function testUpdateIncrementsFromDefault(): void
    {
        $result = Store::update('counter', static fn (int $n): int => $n + 1, 0);

        $this->assertSame(1, $result);
        $this->assertSame(1, Store::get('counter'));
    }

    public function testUpdateReadsExistingValue(): void
    {
        Store::set('counter', 5);
        Store::update('counter', static fn (int $n): int => $n + 1);

        $this->assertSame(6, Store::get('counter'));
    }

    public function testNamespaceIsolation(): void
    {
        Store::set('key', 'value');
        $_SESSION['auth_user'] = 'someone';

        $this->assertArrayHasKey('store.key', $_SESSION);
        $this->assertSame('someone', $_SESSION['auth_user']);
    }

    public function testClearOnlyRemovesStoreKeys(): void
    {
        Store::set('a', 1);
        Store::set('b', 2);
        $_SESSION['auth_user'] = 'someone';

        Store::clear();

        $this->assertFalse(Store::has('a'));
        $this->assertFalse(Store::has('b'));
        $this->assertSame('someone', $_SESSION['auth_user']);
    }
}
