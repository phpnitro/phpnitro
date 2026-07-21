<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine;

/**
 * Flex alignment presets — the DOM/Tailwind equivalent of Flutter's
 * Alignment/AxisAlignment enums (there is no separate rendering-engine
 * concept to model here, just a set of flexbox utility combinations).
 */
final class Alignment
{
    public const TOP_LEFT = 'items-start justify-start';
    public const TOP_CENTER = 'items-start justify-center';
    public const TOP_RIGHT = 'items-start justify-end';
    public const CENTER_LEFT = 'items-center justify-start';
    public const CENTER = 'items-center justify-center';
    public const CENTER_RIGHT = 'items-center justify-end';
    public const BOTTOM_LEFT = 'items-end justify-start';
    public const BOTTOM_CENTER = 'items-end justify-center';
    public const BOTTOM_RIGHT = 'items-end justify-end';
}
