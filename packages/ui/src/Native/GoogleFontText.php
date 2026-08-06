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
 * Renders text in a Google Font, resolved on-device via Android's own
 * Downloadable Fonts API (queries the on-device Google Play Services
 * Fonts provider — the same mechanism Android Studio's "Downloadable
 * fonts" feature uses, see GoogleFontLoader.kt), not a hand-parsed
 * fonts.googleapis.com/css2 response bundled ahead of time. $fontFamily
 * is any family name from https://fonts.google.com ("Roboto Slab",
 * "Playfair Display", "Pacifico"...).
 *
 * Single-line only, deliberately no text-wrapping — TextMetrics.php
 * (what Text's own multi-line wrapping measures against) only knows
 * Roboto's per-character advance widths; wrapping decisions made against
 * Roboto metrics would come out wrong once the real glyphs are a
 * different font's different widths. Pass an explicit $width if this
 * text might otherwise overflow its box, the same escape hatch
 * TextField's own $width param provides for an analogous reason.
 *
 * The font is requested asynchronously — the very first time a given
 * family renders on a device, this draws with the default Roboto until
 * the download resolves (typically a few hundred ms on a warm Play
 * Services fonts cache), then automatically redraws in the real font
 * once cached; every render after that is instant (OS-level cache
 * shared across every app on the device, not just this one).
 */
final class GoogleFontText implements Widget
{
    private Size $size;

    public function __construct(
        private readonly string $text,
        private readonly string $fontFamily,
        private readonly float $fontSize = 16.0,
        private readonly string $color = '#000000',
        private readonly bool $bold = false,
        private readonly ?float $width = null,
    ) {
        $this->size = Size::zero();
    }

    public function layout(Constraints $constraints): Size
    {
        $width = $this->width ?? TextMetrics::width($this->text, $this->fontSize, 0.0, $this->bold);
        $height = TextMetrics::lineHeight($this->fontSize);
        $this->size = $constraints->constrain(new Size($width, $height));

        return $this->size;
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $baselineOffset = $this->fontSize * 0.8;
        $canvas->text($x, $y + $baselineOffset, $this->text, $this->color, $this->fontSize, $this->bold, 0.0, $this->fontFamily);
    }
}
