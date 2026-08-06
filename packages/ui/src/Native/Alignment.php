<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Native;

/**
 * Align's positions — the native-tree equivalent of Engine\Alignment,
 * which is a set of Tailwind flex classes and can't be reused here (there's
 * no DOM/flexbox under a Canvas, Align computes the offset itself).
 */
enum Alignment
{
    case TOP_LEFT;
    case TOP_CENTER;
    case TOP_RIGHT;
    case CENTER_LEFT;
    case CENTER;
    case CENTER_RIGHT;
    case BOTTOM_LEFT;
    case BOTTOM_CENTER;
    case BOTTOM_RIGHT;
}
