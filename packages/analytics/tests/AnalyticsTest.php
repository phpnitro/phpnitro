<?php

namespace Engine\Analytics\Tests;

use Engine\Analytics\Analytics;
use Engine\Database\Database;
use PHPUnit\Framework\TestCase;

final class AnalyticsTest extends TestCase
{
    private string $sqlitePath;

    protected function setUp(): void
    {
        $this->sqlitePath = sys_get_temp_dir() . '/phpnitro-analytics-test-' . uniqid() . '.sqlite';
        Database::useSqlitePath($this->sqlitePath);

        // Analytics::$schemaEnsured is a static that can already be true
        // here — some other test file (order isn't guaranteed) may have
        // used Analytics against ITS OWN sqlite file earlier in this same
        // PHP process (see NativeScreensSmokeTest's identical guard for
        // Preferences, the same underlying cause).
        (new \ReflectionClass(Analytics::class))->getProperty('schemaEnsured')->setValue(null, false);
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(Database::class);
        $reflection->getProperty('connection')->setValue(null, null);
        $reflection->getProperty('sqlitePath')->setValue(null, null);
        @unlink($this->sqlitePath);
    }

    public function testTrackAndRecent(): void
    {
        Analytics::track('screen_view', ['screen' => 'home']);
        Analytics::track('button_tap', ['id' => 'increment']);

        $recent = Analytics::recent();

        $this->assertCount(2, $recent);
        // Most recent first.
        $this->assertSame('button_tap', $recent[0]['event']);
        $this->assertSame(['id' => 'increment'], $recent[0]['properties']);
        $this->assertSame('screen_view', $recent[1]['event']);
        $this->assertSame(['screen' => 'home'], $recent[1]['properties']);
        $this->assertNotEmpty($recent[0]['occurredAt']);
    }

    public function testRecentRespectsLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Analytics::track('tick');
        }

        $this->assertCount(3, Analytics::recent(3));
    }

    public function testCount(): void
    {
        Analytics::track('screen_view', ['screen' => 'home']);
        Analytics::track('screen_view', ['screen' => 'settings']);
        Analytics::track('button_tap');

        $this->assertSame(2, Analytics::count('screen_view'));
        $this->assertSame(1, Analytics::count('button_tap'));
        $this->assertSame(0, Analytics::count('nonexistent'));
    }

    public function testSummary(): void
    {
        Analytics::track('screen_view');
        Analytics::track('screen_view');
        Analytics::track('screen_view');
        Analytics::track('button_tap');

        $summary = Analytics::summary();

        $this->assertSame(['screen_view' => 3, 'button_tap' => 1], $summary);
    }

    public function testClear(): void
    {
        Analytics::track('screen_view');
        Analytics::clear();

        $this->assertSame([], Analytics::recent());
        $this->assertSame(0, Analytics::count('screen_view'));
    }

    public function testTrackWithoutPropertiesDefaultsToEmptyArray(): void
    {
        Analytics::track('app_open');

        $this->assertSame([], Analytics::recent()[0]['properties']);
    }
}
