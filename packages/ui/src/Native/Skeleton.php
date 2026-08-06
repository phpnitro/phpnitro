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
 * A loading placeholder with a real shimmer sweep — Canvas::skeleton()
 * emits its own "skeleton" command type (not a plain "rect") precisely
 * because the sweep is a continuous repaint with no honest single-frame
 * JSON representation, the same "needs real NativeCanvasView.kt support"
 * category Spinner/Confetti were in before they got it — see that
 * method's own docblock for the exact mechanism (a dedicated
 * ValueAnimator, started/stopped on demand, driving a moving gradient
 * shader client-side).
 */
final class Skeleton implements Widget
{
    private Size $size;

    public function __construct(
        private readonly float $width,
        private readonly float $height,
        private readonly float $radius = Tokens::RADIUS_SM,
    ) {
        $this->size = Size::zero();
    }

    /** A circular skeleton (avatar placeholder) — same shape ImageCircle's real content would eventually take. */
    public static function circle(float $diameter): self
    {
        return new self($diameter, $diameter, $diameter / 2);
    }

    /** Convenience for the common "N lines of skeleton text" case. */
    public static function lines(int $count, float $width, float $lineHeight = 14.0, float $gap = 8.0): Widget
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            if ($i > 0) {
                $rows[] = new SizedBox(width: 0.0, height: $gap);
            }
            // Last line runs shorter — the one detail that makes a block
            // of skeleton lines read as "text", not "N identical bars".
            $rows[] = new Skeleton($i === $count - 1 ? $width * 0.6 : $width, $lineHeight);
        }

        return Flex::column($rows, crossAxisAlignment: CrossAxisAlignment::START);
    }

    public function layout(Constraints $constraints): Size
    {
        $this->size = $constraints->constrain(new Size($this->width, $this->height));

        return $this->size;
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        // Tokens::border(), not surfaceMuted() — a screen's own
        // background is very often surfaceMuted() itself (see
        // NativeWidgetsFormsScreen.php), which made a plain-fill skeleton
        // invisible (same color as what's behind it) until that was
        // caught on a real device. border() stays muted but is a
        // distinct shade in both light and dark mode, and gives the
        // sweep something to actually stand out against.
        $canvas->skeleton($x, $y, $this->size->width, $this->size->height, Tokens::border()->toHex(), $this->radius);
    }
}
