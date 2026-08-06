<?php

namespace Engine\Math\Tests;

use Engine\Math\Decimal;
use PHPUnit\Framework\TestCase;

final class DecimalTest extends TestCase
{
    public function testAddIsExactUnlikeFloat(): void
    {
        $result = Decimal::of('0.1')->add('0.2');
        $this->assertSame('0.30', $result->toString());
    }

    public function testSubMulDiv(): void
    {
        $this->assertSame('5.00', Decimal::of('10')->sub('5')->toString());
        $this->assertSame('20.00', Decimal::of('10')->mul('2')->toString());
        $this->assertSame('5.00', Decimal::of('10')->div('2')->toString());
    }

    public function testComparisons(): void
    {
        $this->assertTrue(Decimal::of('10')->equals('10.00'));
        $this->assertTrue(Decimal::of('10')->greaterThan('9.99'));
        $this->assertTrue(Decimal::of('10')->lessThan('10.01'));
    }

    public function testToFloat(): void
    {
        $this->assertSame(10.5, Decimal::of('10.50')->toFloat());
    }

    public function testAcceptsFloatInput(): void
    {
        $this->assertSame('19.99', Decimal::of(19.99)->toString());
    }
}
