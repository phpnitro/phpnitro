<?php

namespace Engine\Math\Tests;

use Engine\Math\UnitConverter;
use PHPUnit\Framework\TestCase;

final class UnitConverterTest extends TestCase
{
    public function testDistance(): void
    {
        $this->assertEqualsWithDelta(6.21371, UnitConverter::kmToMiles(10), 0.001);
        $this->assertEqualsWithDelta(16.0934, UnitConverter::milesToKm(10), 0.001);
    }

    public function testWeight(): void
    {
        $this->assertEqualsWithDelta(22.0462, UnitConverter::kgToPounds(10), 0.001);
        $this->assertEqualsWithDelta(4.53592, UnitConverter::poundsToKg(10), 0.001);
    }

    public function testTemperature(): void
    {
        $this->assertEqualsWithDelta(32.0, UnitConverter::celsiusToFahrenheit(0), 0.001);
        $this->assertEqualsWithDelta(100.0, UnitConverter::celsiusToFahrenheit(37.7778), 0.01);
        $this->assertEqualsWithDelta(0.0, UnitConverter::fahrenheitToCelsius(32), 0.001);
    }

    public function testBytesToHuman(): void
    {
        $this->assertSame('1 o', UnitConverter::bytesToHuman(1, 0));
        $this->assertSame('1.5 Ko', UnitConverter::bytesToHuman(1536));
        $this->assertSame('1 Mo', UnitConverter::bytesToHuman(1024 * 1024, 0));
    }
}
