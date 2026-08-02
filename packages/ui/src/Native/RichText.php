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
 * Flutter's Text.rich(TextSpan(...)) — mixed styles (bold, color, size,
 * even a tappable link) flowing as ONE wrapped paragraph, not one
 * Text per run stacked vertically. Text's word-wrap only ever
 * had a single style for the whole string; this tokenizes every span into
 * words tagged with their own style, then greedy-wraps across the WHOLE
 * token stream — a span boundary is where the style changes, not where a
 * line is allowed to break, so "bold **word** and a link" wraps exactly
 * like a plain sentence would.
 *
 * Baseline/line-height per line comes from the LARGEST span on that
 * line — a single oversized word doesn't get clipped by neighbors sized
 * for the base font, at the cost of not perfectly matching how a real
 * text-shaping engine would sub-position mixed baselines (out of scope:
 * this is real text layout, not full typography).
 */
final class RichText implements Widget
{
    /**
     * @var array<int, array<int, array{word: string, span: TextSpan, x: float, size: float}>>
     */
    private array $lines = [];

    /** @var array<int, float> */
    private array $lineHeights = [];

    /** @var array<int, float> The tallest span's font size on each line — what its shared baseline is anchored to. */
    private array $lineBaselineSizes = [];

    /**
     * @param array<int, TextSpan> $spans
     */
    public function __construct(
        private readonly array $spans,
        private readonly float $fontSize = 16.0,
        private readonly string $color = '#000000',
    ) {
    }

    private function resolvedSize(TextSpan $span): float
    {
        return $span->size ?? $this->fontSize;
    }

    private function resolvedColor(TextSpan $span): string
    {
        return $span->color ?? $this->color;
    }

    private function resolvedBold(TextSpan $span): bool
    {
        return $span->bold ?? false;
    }

    private function resolvedLetterSpacing(TextSpan $span): float
    {
        return $span->letterSpacing ?? 0.0;
    }

    public function layout(Constraints $constraints): Size
    {
        $maxWidth = $constraints->hasBoundedWidth() ? $constraints->maxWidth : Constraints::INFINITY;

        /** @var array<int, array{word: string, span: TextSpan}> $tokens */
        $tokens = [];
        foreach ($this->spans as $span) {
            foreach (preg_split('/\s+/', trim($span->text)) ?: [] as $word) {
                if ($word === '') {
                    continue;
                }
                $tokens[] = ['word' => $word, 'span' => $span];
            }
        }

        $this->lines = [];
        $this->lineHeights = [];
        /** @var array<int, array{word: string, span: TextSpan, x: float, size: float}> $currentLine */
        $currentLine = [];
        $currentWidth = 0.0;
        $maxLineWidth = 0.0;

        $flushLine = function () use (&$currentLine, &$currentWidth, &$maxLineWidth): void {
            if ($currentLine === []) {
                return;
            }
            $tallest = 0.0;
            foreach ($currentLine as $entry) {
                $tallest = max($tallest, $entry['size']);
            }
            $this->lines[] = $currentLine;
            $this->lineHeights[] = TextMetrics::lineHeight($tallest);
            $this->lineBaselineSizes[] = $tallest;
            $maxLineWidth = max($maxLineWidth, $currentWidth);
            $currentLine = [];
            $currentWidth = 0.0;
        };

        foreach ($tokens as $token) {
            $span = $token['span'];
            $size = $this->resolvedSize($span);
            $bold = $this->resolvedBold($span);
            $letterSpacing = $this->resolvedLetterSpacing($span);
            $wordWidth = TextMetrics::width($token['word'], $size, $letterSpacing, $bold);
            $spaceWidth = $currentLine === [] ? 0.0 : TextMetrics::width(' ', $size, $letterSpacing, $bold);

            if ($currentLine !== [] && is_finite($maxWidth) && $currentWidth + $spaceWidth + $wordWidth > $maxWidth) {
                $flushLine();
                $spaceWidth = 0.0;
            }

            $x = $currentWidth + $spaceWidth;
            $currentLine[] = ['word' => $token['word'], 'span' => $span, 'x' => $x, 'size' => $size];
            $currentWidth = $x + $wordWidth;
        }
        $flushLine();

        $height = array_sum($this->lineHeights);

        return $constraints->constrain(new Size($maxLineWidth, $height));
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $cursorY = 0.0;
        foreach ($this->lines as $lineIndex => $line) {
            $lineHeight = $this->lineHeights[$lineIndex];
            // Same "~80% of font size" baseline heuristic Text uses,
            // anchored to this line's own TALLEST span so a smaller word
            // sharing the line still sits on one common baseline instead
            // of its own smaller one.
            $baselineOffset = $this->lineBaselineSizes[$lineIndex] * 0.8;
            foreach ($line as $entry) {
                $span = $entry['span'];
                $size = $entry['size'];
                $bold = $this->resolvedBold($span);
                $letterSpacing = $this->resolvedLetterSpacing($span);
                $wordWidth = TextMetrics::width($entry['word'], $size, $letterSpacing, $bold);

                $canvas->text(
                    $x + $entry['x'],
                    $y + $cursorY + $baselineOffset,
                    $entry['word'],
                    $this->resolvedColor($span),
                    $size,
                    $bold,
                    $letterSpacing,
                );

                if ($span->action !== null) {
                    $canvas->hitRegion($x + $entry['x'], $y + $cursorY, $wordWidth, $lineHeight, $span->action);
                }
            }
            $cursorY += $lineHeight;
        }
    }
}
