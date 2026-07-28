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
 * Text measurement is the genuinely hard part of a from-scratch layout
 * engine (see docs/proposals/moteur-rendu-natif.md's cost breakdown) — real
 * per-glyph widths live in the font, and the font lives on the native side
 * (Android's default Roboto), not in this PHP process. Rather than block
 * the whole layout engine on a synchronous PHP<->native measurement
 * round-trip, this uses a calibrated average-character-width heuristic:
 * good enough to make Column/Row/Text/Container layout together correctly
 * today, with a known error margin (mixed-width fonts mean any given line
 * can be measured a few percent wide or narrow of what Canvas.drawText()
 * actually occupies).
 *
 * Revisit when a real native measurement pass is built (phase 4 of the
 * roadmap) — until then, this is the one place that approximation lives,
 * so fixing it later means changing one file, not every RenderText call
 * site.
 */
final class TextMetrics
{
    private const AVG_CHAR_WIDTH_RATIO = 0.52;
    private const LINE_HEIGHT_RATIO = 1.25;

    public static function lineHeight(float $fontSize): float
    {
        return $fontSize * self::LINE_HEIGHT_RATIO;
    }

    public static function width(string $text, float $fontSize): float
    {
        return mb_strlen($text) * $fontSize * self::AVG_CHAR_WIDTH_RATIO;
    }

    /**
     * Greedy word-wrap: keeps adding words to the current line while it
     * fits within $maxWidth, otherwise starts a new one. A single word
     * longer than $maxWidth is left to overflow rather than broken
     * mid-word (matches Flutter's default Text behavior without an
     * explicit overflow strategy).
     *
     * @return array<int, string>
     */
    public static function wrap(string $text, float $fontSize, float $maxWidth): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        if ($words === ['']) {
            return [''];
        }

        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if ($current !== '' && self::width($candidate, $fontSize) > $maxWidth) {
                $lines[] = $current;
                $current = $word;
                continue;
            }
            $current = $candidate;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }
}
