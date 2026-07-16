<?php

namespace Engine\Tests;

use Engine\Router;
use Engine\Screen;
use Engine\Text;
use Engine\Widget;
use PHPUnit\Framework\TestCase;

final class FakeScreen extends Screen
{
    protected function initialState(): array
    {
        return [];
    }

    public function build(): Widget
    {
        return Text::make('fake');
    }
}

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router([
            '/' => FakeScreen::class,
            '/settings' => FakeScreen::class,
            '/product/{id}' => FakeScreen::class,
        ]);
    }

    public function testResolvesStaticRoute(): void
    {
        $resolved = $this->router->resolve('/settings');

        $this->assertSame(FakeScreen::class, $resolved['class']);
        $this->assertSame([], $resolved['params']);
    }

    public function testResolvesRootWithTrailingSlashVariants(): void
    {
        $this->assertSame(FakeScreen::class, $this->router->resolve('/')['class']);
        $this->assertSame(FakeScreen::class, $this->router->resolve('/settings/')['class']);
    }

    public function testExtractsRouteParameters(): void
    {
        $resolved = $this->router->resolve('/product/42');

        $this->assertSame(['id' => '42'], $resolved['params']);
    }

    public function testThrowsOnUnknownPath(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->router->resolve('/nope');
    }
}
