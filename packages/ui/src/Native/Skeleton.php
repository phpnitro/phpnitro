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
 * A loading placeholder — a flat rounded rect in place of content that
 * hasn't arrived yet (a real Flutter/RN skeleton screen, minus the
 * shimmer sweep animation those add: that needs a continuously-repainting
 * gradient, the same category of "continuous animation with no per-frame
 * server round-trip" primitive Spinner/Confetti needed real
 * NativeCanvasView.kt support for — a real future addition, not
 * attempted here). Static is still a real, honest loading affordance on
 * its own — every mainstream framework shipped exactly this before
 * adding the shimmer.
 */
final class Skeleton implements Widget
{
    private readonly Widget $content;

    public function __construct(float $width, float $height, float $radius = Tokens::RADIUS_SM)
    {
        // Tokens::border(), not surfaceMuted() — a screen's own
        // background is very often surfaceMuted() itself (see
        // NativeWidgetsFormsScreen.php), which made the skeleton
        // invisible (same color as what's behind it) until this was
        // caught on a real device. border() stays muted but is a
        // distinct shade in both light and dark mode.
        $this->content = new Container(width: $width, height: $height, background: Tokens::border(), radius: $radius);
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
        return $this->content->layout($constraints);
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $this->content->paint($canvas, $x, $y);
    }
}
