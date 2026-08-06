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
 * Flutter's Curves class, the small subset with a direct built-in Android
 * Interpolator equivalent (see NativeCanvasView.kt's curveInterpolator()).
 * Threaded through Hero/Animated into
 * Canvas::beginHero()'s $curve — every hero flight already runs on
 * one shared, linear-time ValueAnimator (NativeCanvasView.kt's
 * startHeroTransition()), so a per-tag curve is applied by reshaping that
 * same 0..1 progress value at draw time (drawHeroTransition()), not by
 * running a separate animator per tag.
 *
 * ELASTIC has no exact Android built-in — mapped to OvershootInterpolator,
 * the closest stock "overshoots past the target then settles back" curve.
 */
enum Curve
{
    case LINEAR;
    case EASE_IN;
    case EASE_OUT;
    case EASE_IN_OUT;
    case BOUNCE;
    case ELASTIC;
}
