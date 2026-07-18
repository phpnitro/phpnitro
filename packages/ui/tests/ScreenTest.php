<?php

namespace Engine\Tests;

use Engine\Screen;
use Engine\Text;
use Engine\Widget;
use PHPUnit\Framework\TestCase;

final class CounterScreen extends Screen
{
    protected function initialState(): array
    {
        return ['count' => 0, 'last' => null];
    }

    protected function onIncrement(array $data): void
    {
        $this->state['count']++;
        $this->state['last'] = $data['note'] ?? null;
    }

    protected function onFinish(): string
    {
        return '/done';
    }

    public function build(): Widget
    {
        return Text::make((string) $this->state['count']);
    }

    public function count(): int
    {
        return $this->state['count'];
    }
}

final class ScreenTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testActionMutatesStateAndPersistsToSession(): void
    {
        $screen = new CounterScreen();
        $screen->handle('increment');

        $fresh = new CounterScreen();

        $this->assertSame(1, $fresh->count());
    }

    public function testActionReceivesSubmittedData(): void
    {
        $screen = new CounterScreen();
        $screen->handle('increment', ['note' => 'hello']);

        $this->assertSame('hello', $_SESSION[CounterScreen::class . ':']['last']);
    }

    public function testActionCanRedirect(): void
    {
        $this->assertSame('/done', (new CounterScreen())->handle('finish'));
        $this->assertNull((new CounterScreen())->handle('increment'));
    }

    public function testUnknownActionThrows(): void
    {
        $this->expectException(\RuntimeException::class);

        (new CounterScreen())->handle('hack');
    }

    public function testRouteParamsIsolateState(): void
    {
        $a = new CounterScreen(['id' => '1']);
        $a->handle('increment');

        $b = new CounterScreen(['id' => '2']);

        $this->assertSame(0, $b->count());
    }

    public function testShowsBottomNavDefaultsToTrue(): void
    {
        $this->assertTrue((new CounterScreen())->showsBottomNav());
    }
}
