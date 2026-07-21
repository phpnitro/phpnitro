<?php

namespace Engine\Preferences\Tests;

use Engine\Database\Database;
use Engine\Preferences\Preferences;
use PHPUnit\Framework\TestCase;

final class PreferencesTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/phpnitro-preferences-test-' . uniqid() . '.sqlite';
        Database::useSqlitePath($this->path);

        $reflection = new \ReflectionClass(Preferences::class);
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

    public function testGetReturnsDefaultWhenKeyMissing(): void
    {
        $this->assertSame('fallback', Preferences::get('missing', 'fallback'));
        $this->assertNull(Preferences::get('missing'));
    }

    public function testSetThenGetRoundTripsScalarValues(): void
    {
        Preferences::set('theme', 'dark');
        Preferences::set('volume', 7);
        Preferences::set('onboarded', true);

        $this->assertSame('dark', Preferences::get('theme'));
        $this->assertSame(7, Preferences::get('volume'));
        $this->assertTrue(Preferences::get('onboarded'));
    }

    public function testSetThenGetRoundTripsArrayValues(): void
    {
        Preferences::set('favorites', [1, 2, 3]);

        $this->assertSame([1, 2, 3], Preferences::get('favorites'));
    }

    public function testSetOverwritesExistingValue(): void
    {
        Preferences::set('theme', 'dark');
        Preferences::set('theme', 'light');

        $this->assertSame('light', Preferences::get('theme'));
    }

    public function testHasReflectsPresence(): void
    {
        $this->assertFalse(Preferences::has('theme'));

        Preferences::set('theme', 'dark');

        $this->assertTrue(Preferences::has('theme'));
    }

    public function testRemoveDeletesKey(): void
    {
        Preferences::set('theme', 'dark');
        Preferences::remove('theme');

        $this->assertFalse(Preferences::has('theme'));
        $this->assertNull(Preferences::get('theme'));
    }

    public function testClearRemovesEverything(): void
    {
        Preferences::set('a', 1);
        Preferences::set('b', 2);
        Preferences::clear();

        $this->assertFalse(Preferences::has('a'));
        $this->assertFalse(Preferences::has('b'));
    }
}
