<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Countries;

enum Continent: string
{
    case AFRICA = 'AFRICA';
    case ASIA = 'ASIA';
    case EUROPE = 'EUROPE';
    case NORTH_AMERICA = 'NORTH_AMERICA';
    case SOUTH_AMERICA = 'SOUTH_AMERICA';
    case OCEANIA = 'OCEANIA';
    case ANTARCTICA = 'ANTARCTICA';

    public function label(): string
    {
        return match ($this) {
            self::AFRICA => 'Afrique',
            self::ASIA => 'Asie',
            self::EUROPE => 'Europe',
            self::NORTH_AMERICA => 'Amérique du Nord',
            self::SOUTH_AMERICA => 'Amérique du Sud',
            self::OCEANIA => 'Océanie',
            self::ANTARCTICA => 'Antarctique',
        };
    }
}
