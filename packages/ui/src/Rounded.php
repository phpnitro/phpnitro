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

enum Rounded: string
{
    case NONE = 'rounded-none';
    case SM = 'rounded-sm';
    case MD = 'rounded-md';
    case LG = 'rounded-lg';
    case XL = 'rounded-xl';
    case FULL = 'rounded-full';
}
