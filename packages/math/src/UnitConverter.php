<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Math;

/**
 * Common unit conversions — covers both "convert" and "convertlib" from
 * the original package list (the two were duplicates of each other).
 */
final class UnitConverter
{
    public static function kmToMiles(float $km): float
    {
        return $km * 0.621371;
    }

    public static function milesToKm(float $miles): float
    {
        return $miles / 0.621371;
    }

    public static function kgToPounds(float $kg): float
    {
        return $kg * 2.20462;
    }

    public static function poundsToKg(float $pounds): float
    {
        return $pounds / 2.20462;
    }

    public static function celsiusToFahrenheit(float $celsius): float
    {
        return $celsius * 9 / 5 + 32;
    }

    public static function fahrenheitToCelsius(float $fahrenheit): float
    {
        return ($fahrenheit - 32) * 5 / 9;
    }

    public static function metersToFeet(float $meters): float
    {
        return $meters * 3.28084;
    }

    public static function feetToMeters(float $feet): float
    {
        return $feet / 3.28084;
    }

    public static function litersToGallons(float $liters): float
    {
        return $liters * 0.264172;
    }

    public static function gallonsToLiters(float $gallons): float
    {
        return $gallons / 0.264172;
    }

    /** "1536" -> "1.5 Ko", same scale humans read file sizes at (1024-based, not 1000-based SI). */
    public static function bytesToHuman(int $bytes, int $decimals = 2): string
    {
        $units = ['o', 'Ko', 'Mo', 'Go', 'To', 'Po'];
        $index = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $index = max(0, min($index, count($units) - 1));

        return round($bytes / (1024 ** $index), $decimals) . ' ' . $units[$index];
    }
}
