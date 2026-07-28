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
 * round-trip, this buckets characters by approximate Roboto advance width
 * (narrow like "i"/"l", wide like "m"/"M", everything else in between)
 * instead of a single flat average — noticeably tighter wrapping/box
 * sizing than a uniform ratio, though still not exact glyph metrics.
 *
 * Revisit when a real native measurement pass is built (phase 4 of the
 * roadmap) — until then, this is the one place that approximation lives,
 * so fixing it later means changing one file, not every RenderText call
 * site.
 */
final class TextMetrics
{
    private const LINE_HEIGHT_RATIO = 1.25;

    /** @var array<string, float> Advance width as a fraction of font size, by character bucket. */
    private const WIDTH_BUCKETS = [
        'narrow' => 0.24,   // i l j I . , ' ! | : ; ( ) [ ] " ` space
        'compact' => 0.34,  // f t r - / \ 1
        'regular' => 0.52,  // most lowercase, digits, default punctuation
        'upperRegular' => 0.64, // most uppercase
        'wide' => 0.82,     // m w M W % @ &
    ];

    private const NARROW_CHARS = " iIlj.,'!|:;()[]\"`";
    private const COMPACT_CHARS = 'ftr-/\\1';
    private const WIDE_CHARS = 'mwMW%@&';

    public static function lineHeight(float $fontSize): float
    {
        return $fontSize * self::LINE_HEIGHT_RATIO;
    }

    public static function width(string $text, float $fontSize, float $letterSpacing = 0.0): float
    {
        $chars = mb_str_split($text);
        $total = 0.0;
        foreach ($chars as $char) {
            $total += self::charWidthRatio($char);
        }

        // Android's Paint.letterSpacing is an em-per-character value added
        // between glyphs — matching that here keeps tracked/uppercase
        // caption labels (e.g. a small-caps section header) from
        // under-measuring and wrapping too early.
        return $total * $fontSize + count($chars) * $letterSpacing * $fontSize;
    }

    private static function charWidthRatio(string $char): float
    {
        if (str_contains(self::NARROW_CHARS, $char)) {
            return self::WIDTH_BUCKETS['narrow'];
        }
        if (str_contains(self::COMPACT_CHARS, $char)) {
            return self::WIDTH_BUCKETS['compact'];
        }
        if (str_contains(self::WIDE_CHARS, $char)) {
            return self::WIDTH_BUCKETS['wide'];
        }
        if (ctype_upper($char)) {
            return self::WIDTH_BUCKETS['upperRegular'];
        }

        return self::WIDTH_BUCKETS['regular'];
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
    public static function wrap(string $text, float $fontSize, float $maxWidth, float $letterSpacing = 0.0): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        if ($words === ['']) {
            return [''];
        }

        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if ($current !== '' && self::width($candidate, $fontSize, $letterSpacing) > $maxWidth) {
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
