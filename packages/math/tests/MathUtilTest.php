<?php

namespace Engine\Math\Tests;

use Engine\Math\MathUtil;
use PHPUnit\Framework\TestCase;

final class MathUtilTest extends TestCase
{
    public function testClamp(): void
    {
        $this->assertSame(5.0, MathUtil::clamp(5.0, 0.0, 10.0));
        $this->assertSame(0.0, MathUtil::clamp(-3.0, 0.0, 10.0));
        $this->assertSame(10.0, MathUtil::clamp(99.0, 0.0, 10.0));
    }

    public function testClampInt(): void
    {
        $this->assertSame(5, MathUtil::clampInt(5, 0, 10));
        $this->assertSame(0, MathUtil::clampInt(-3, 0, 10));
        $this->assertSame(10, MathUtil::clampInt(99, 0, 10));
    }

    public function testLerp(): void
    {
        $this->assertSame(5.0, MathUtil::lerp(0.0, 10.0, 0.5));
        $this->assertSame(0.0, MathUtil::lerp(0.0, 10.0, 0.0));
        $this->assertSame(10.0, MathUtil::lerp(0.0, 10.0, 1.0));
        $this->assertSame(15.0, MathUtil::lerp(0.0, 10.0, 1.5));
    }

    public function testInverseLerp(): void
    {
        $this->assertSame(0.5, MathUtil::inverseLerp(0.0, 10.0, 5.0));
        $this->assertSame(0.0, MathUtil::inverseLerp(5.0, 5.0, 5.0));
    }

    public function testMap(): void
    {
        $this->assertSame(50.0, MathUtil::map(5.0, 0.0, 10.0, 0.0, 100.0));
        $this->assertSame(0.0, MathUtil::map(0.0, 0.0, 10.0, 0.0, 100.0));
    }

    public function testRoundTo(): void
    {
        $this->assertSame(1.23, MathUtil::roundTo(1.2345, 2));
        $this->assertSame(1.0, MathUtil::roundTo(1.4));
    }

    public function testRoundToStep(): void
    {
        $this->assertSame(25.0, MathUtil::roundToStep(23.0, 5.0));
        $this->assertSame(20.0, MathUtil::roundToStep(22.0, 5.0));
        $this->assertSame(23.0, MathUtil::roundToStep(23.0, 0.0));
    }

    public function testIsBetween(): void
    {
        $this->assertTrue(MathUtil::isBetween(5.0, 0.0, 10.0));
        $this->assertTrue(MathUtil::isBetween(0.0, 0.0, 10.0));
        $this->assertTrue(MathUtil::isBetween(10.0, 0.0, 10.0));
        $this->assertFalse(MathUtil::isBetween(11.0, 0.0, 10.0));
    }

    public function testPercentageOf(): void
    {
        $this->assertSame(15.0, MathUtil::percentageOf(30.0, 200.0));
        $this->assertSame(0.0, MathUtil::percentageOf(30.0, 0.0));
    }

    public function testRandomIntStaysInRange(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $value = MathUtil::randomInt(1, 6);
            $this->assertGreaterThanOrEqual(1, $value);
            $this->assertLessThanOrEqual(6, $value);
        }
    }

    public function testRandomFloatStaysInRange(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $value = MathUtil::randomFloat(1.0, 2.0);
            $this->assertGreaterThanOrEqual(1.0, $value);
            $this->assertLessThanOrEqual(2.0, $value);
        }
    }
}
