<?php

namespace Engine\Tests;

use Engine\Native\Router;
use Engine\Native\Text;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    protected function setUp(): void
    {
        Router::reset();
    }

    public function testHasIsFalseForAnUnregisteredScreen(): void
    {
        $this->assertFalse(Router::has('nonexistent'));
        $this->assertNull(Router::build('nonexistent'));
    }

    public function testRegisterAndBuild(): void
    {
        Router::register('greeting', static fn () => new Text('hello', 14.0, '#000000'));

        $this->assertTrue(Router::has('greeting'));
        $this->assertInstanceOf(Text::class, Router::build('greeting'));
    }

    public function testBuilderIsCalledFreshEveryTime(): void
    {
        $calls = 0;
        Router::register('counted', static function () use (&$calls) {
            $calls++;

            return new Text((string) $calls, 14.0, '#000000');
        });

        Router::build('counted');
        Router::build('counted');

        $this->assertSame(2, $calls);
    }

    public function testReRegisteringOverwrites(): void
    {
        Router::register('screen', static fn () => new Text('first', 14.0, '#000000'));
        Router::register('screen', static fn () => new Text('second', 14.0, '#000000'));

        $canvas = new \Engine\Native\Canvas();
        $widget = Router::build('screen');
        $widget->layout(new \Engine\Native\Constraints(0, 360, 0, \Engine\Native\Constraints::INFINITY));
        $widget->paint($canvas, 0, 0);

        $this->assertStringContainsString('second', $canvas->toJson());
    }
}
