<?php

namespace Engine\Math\Tests;

use Engine\Math\Stats;
use PHPUnit\Framework\TestCase;

final class StatsTest extends TestCase
{
    public function testSum(): void
    {
        $this->assertSame(15.0, Stats::sum([1, 2, 3, 4, 5]));
        $this->assertSame(0.0, Stats::sum([]));
    }

    public function testMean(): void
    {
        $this->assertSame(3.0, Stats::mean([1, 2, 3, 4, 5]));
        $this->assertSame(0.0, Stats::mean([]));
    }

    public function testMedianOddCount(): void
    {
        $this->assertSame(3.0, Stats::median([5, 1, 3, 2, 4]));
    }

    public function testMedianEvenCount(): void
    {
        $this->assertSame(2.5, Stats::median([1, 2, 3, 4]));
    }

    public function testMedianEmpty(): void
    {
        $this->assertSame(0.0, Stats::median([]));
    }

    public function testMode(): void
    {
        $this->assertSame([2.0], Stats::mode([1, 2, 2, 3]));
    }

    public function testModeTie(): void
    {
        $this->assertEqualsCanonicalizing([1.0, 2.0], Stats::mode([1, 1, 2, 2]));
    }

    public function testModeEmpty(): void
    {
        $this->assertSame([], Stats::mode([]));
    }

    public function testMinMaxRange(): void
    {
        $this->assertSame(1.0, Stats::min([5, 1, 3]));
        $this->assertSame(5.0, Stats::max([5, 1, 3]));
        $this->assertSame(4.0, Stats::range([5, 1, 3]));
    }

    public function testMinMaxEmpty(): void
    {
        $this->assertSame(0.0, Stats::min([]));
        $this->assertSame(0.0, Stats::max([]));
        $this->assertSame(0.0, Stats::range([]));
    }

    public function testPopulationVariance(): void
    {
        $this->assertEqualsWithDelta(4.0, Stats::variance([2, 4, 4, 4, 5, 5, 7, 9]), 0.01);
    }

    public function testSampleVariance(): void
    {
        $this->assertEqualsWithDelta(4.571, Stats::variance([2, 4, 4, 4, 5, 5, 7, 9], sample: true), 0.01);
    }

    public function testVarianceInsufficientData(): void
    {
        $this->assertSame(0.0, Stats::variance([]));
        $this->assertSame(0.0, Stats::variance([5], sample: true));
    }

    public function testStandardDeviation(): void
    {
        $this->assertEqualsWithDelta(2.0, Stats::standardDeviation([2, 4, 4, 4, 5, 5, 7, 9]), 0.1);
    }
}
