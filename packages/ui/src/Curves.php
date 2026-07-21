<?php

namespace Engine;

/**
 * Named easing curves, passed as the `curve` argument to animated widgets
 * (see FadeIn) — CSS timing-function strings under Flutter-familiar names,
 * not a real animatable-value curve system (there's no property tweening
 * here, only CSS keyframes/transitions).
 */
final class Curves
{
    public const LINEAR = 'linear';
    public const EASE = 'ease';
    public const EASE_IN = 'ease-in';
    public const EASE_OUT = 'ease-out';
    public const EASE_IN_OUT = 'ease-in-out';

    /** Material Design's standard curve (fast start, slow settle). */
    public const FAST_OUT_SLOW_IN = 'cubic-bezier(0.4, 0, 0.2, 1)';

    /** Overshoots slightly past the target before settling — a "pop". */
    public const OVERSHOOT = 'cubic-bezier(0.34, 1.56, 0.64, 1)';
}
