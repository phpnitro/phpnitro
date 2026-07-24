<?php

namespace Engine\Tests;

use Engine\Navigation;
use Engine\Navigator;
use PHPUnit\Framework\TestCase;

final class NavigationTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_PHPX_PARTIAL'], $_SERVER['HTTP_REFERER']);
    }

    public function testIsPartialIsFalseWithoutTheHeader(): void
    {
        $this->assertFalse(Navigation::isPartial());
    }

    public function testIsPartialIsTrueWhenNavJsHeaderIsSet(): void
    {
        $_SERVER['HTTP_X_PHPX_PARTIAL'] = '1';

        $this->assertTrue(Navigation::isPartial());
    }

    public function testNavigatorToReturnsThePathAsIs(): void
    {
        $this->assertSame('/settings', Navigator::to('/settings'));
    }

    public function testNavigatorBackUsesRefererWhenPresent(): void
    {
        $_SERVER['HTTP_REFERER'] = '/cart';

        $this->assertSame('/cart', Navigator::back());
    }

    public function testNavigatorBackFallsBackWhenNoReferer(): void
    {
        unset($_SERVER['HTTP_REFERER']);

        $this->assertSame('/', Navigator::back());
        $this->assertSame('/home', Navigator::back('/home'));
    }

    public function testNavigatorLinkBuildsALinkWidget(): void
    {
        $html = Navigator::link('Retour', '/cart')->render();

        $this->assertStringContainsString('href="/cart"', $html);
        $this->assertStringContainsString('>Retour<', $html);
    }
}
