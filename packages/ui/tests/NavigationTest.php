<?php

namespace Engine\Tests;

use Engine\Navigation;
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

}
