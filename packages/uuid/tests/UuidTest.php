<?php

namespace Engine\Uuid\Tests;

use Engine\Uuid\Uuid;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    public function testV4HasCorrectShapeAndVersion(): void
    {
        $uuid = Uuid::v4();
        $this->assertTrue(Uuid::isValid($uuid));
        $this->assertSame('4', $uuid[14]);
        $this->assertContains($uuid[19], ['8', '9', 'a', 'b']);
    }

    public function testV4IsRandomEachTime(): void
    {
        $this->assertNotSame(Uuid::v4(), Uuid::v4());
    }

    public function testV7HasCorrectShapeAndVersion(): void
    {
        $uuid = Uuid::v7();
        $this->assertTrue(Uuid::isValid($uuid));
        $this->assertSame('7', $uuid[14]);
        $this->assertContains($uuid[19], ['8', '9', 'a', 'b']);
    }

    public function testV7SortsChronologically(): void
    {
        $first = Uuid::v7();
        usleep(2000);
        $second = Uuid::v7();
        $this->assertLessThan(0, strcmp($first, $second));
    }

    public function testIsValidRejectsGarbage(): void
    {
        $this->assertFalse(Uuid::isValid('not-a-uuid'));
        $this->assertFalse(Uuid::isValid(''));
    }
}
