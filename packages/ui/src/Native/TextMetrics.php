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
 * Real per-character advance widths (fraction of em) for Roboto Regular
 * and Bold — parsed directly from the font's own hmtx/cmap tables (see
 * the generator note below), not a hand-picked bucket heuristic. This is
 * as precise as Android's Canvas.measureText() itself for plain Latin/
 * French text: the same numbers the actual renderer uses, just computed
 * ahead of time in PHP instead of round-tripping to native at layout
 * time. Ligatures and complex script shaping aren't modeled (Roboto's
 * Latin set barely uses either), and a character outside this table
 * falls back to a reasonable average-width estimate.
 *
 * Regenerate wholesale from Roboto-Regular.ttf/Roboto-Bold.ttf if the
 * bundled font ever changes — don't hand-edit entries. The generator
 * reads 'head' (unitsPerEm), 'hhea' (numOfLongHorMetrics), 'cmap'
 * (format 4, codepoint -> glyph id) and 'hmtx' (glyph id -> advance
 * width), then divides advance/unitsPerEm per character.
 */
final class TextMetrics
{
    private const LINE_HEIGHT_RATIO = 1.25;
    private const FALLBACK_WIDTH = 0.55;

    /** @var array<string, float> */
    private const WIDTHS_REGULAR = [
        ' ' => 0.248,
        '!' => 0.2578,
        '"' => 0.3203,
        '#' => 0.6157,
        '$' => 0.562,
        '%' => 0.7324,
        '&' => 0.6221,
        '\'' => 0.1748,
        '(' => 0.3423,
        ')' => 0.3481,
        '*' => 0.4307,
        '+' => 0.5674,
        ',' => 0.1968,
        '-' => 0.2764,
        '.' => 0.2637,
        '/' => 0.4126,
        '0' => 0.562,
        '1' => 0.562,
        '2' => 0.562,
        '3' => 0.562,
        '4' => 0.562,
        '5' => 0.562,
        '6' => 0.562,
        '7' => 0.562,
        '8' => 0.562,
        '9' => 0.562,
        ':' => 0.2422,
        ';' => 0.2114,
        '<' => 0.5083,
        '=' => 0.5488,
        '>' => 0.5229,
        '?' => 0.4727,
        '@' => 0.8979,
        'A' => 0.6523,
        'B' => 0.623,
        'C' => 0.6509,
        'D' => 0.6563,
        'E' => 0.5684,
        'F' => 0.5527,
        'G' => 0.6812,
        'H' => 0.7134,
        'I' => 0.272,
        'J' => 0.5518,
        'K' => 0.6274,
        'L' => 0.5386,
        'M' => 0.873,
        'N' => 0.7134,
        'O' => 0.688,
        'P' => 0.6309,
        'Q' => 0.688,
        'R' => 0.6162,
        'S' => 0.5938,
        'T' => 0.5967,
        'U' => 0.6484,
        'V' => 0.6367,
        'W' => 0.8872,
        'X' => 0.627,
        'Y' => 0.6006,
        'Z' => 0.5991,
        '[' => 0.2651,
        '\\' => 0.4106,
        ']' => 0.2651,
        '^' => 0.418,
        '_' => 0.4512,
        '`' => 0.3091,
        'a' => 0.5439,
        'b' => 0.5615,
        'c' => 0.5234,
        'd' => 0.564,
        'e' => 0.5303,
        'f' => 0.3477,
        'g' => 0.5615,
        'h' => 0.5508,
        'i' => 0.2432,
        'j' => 0.2393,
        'k' => 0.5068,
        'l' => 0.2432,
        'm' => 0.877,
        'n' => 0.5522,
        'o' => 0.5703,
        'p' => 0.5615,
        'q' => 0.5684,
        'r' => 0.3389,
        's' => 0.5161,
        't' => 0.3271,
        'u' => 0.5513,
        'v' => 0.4844,
        'w' => 0.7515,
        'x' => 0.4961,
        'y' => 0.4731,
        'z' => 0.4961,
        '{' => 0.3384,
        '|' => 0.2441,
        '}' => 0.3384,
        '~' => 0.6802,
        'à' => 0.5439,
        'â' => 0.5439,
        'ä' => 0.5439,
        'é' => 0.5303,
        'è' => 0.5303,
        'ê' => 0.5303,
        'ë' => 0.5303,
        'î' => 0.2476,
        'ï' => 0.2476,
        'ô' => 0.5703,
        'ö' => 0.5703,
        'ù' => 0.5513,
        'û' => 0.5513,
        'ü' => 0.5513,
        'ç' => 0.5234,
        'À' => 0.6523,
        'Â' => 0.6523,
        'Ä' => 0.6523,
        'É' => 0.5684,
        'È' => 0.5684,
        'Ê' => 0.5684,
        'Ë' => 0.5684,
        'Î' => 0.272,
        'Ï' => 0.272,
        'Ô' => 0.688,
        'Ö' => 0.688,
        'Ù' => 0.6484,
        'Û' => 0.6484,
        'Ü' => 0.6484,
        'Ç' => 0.6509,
        '—' => 0.7808,
        '–' => 0.6563,
        '’' => 0.2002,
        '‘' => 0.2002,
        '“' => 0.3535,
        '”' => 0.3574,
        '•' => 0.3374,
        '…' => 0.6689,
    ];

    /** @var array<string, float> */
    private const WIDTHS_BOLD = [
        ' ' => 0.2485,
        '!' => 0.2705,
        '"' => 0.3184,
        '#' => 0.5923,
        '$' => 0.5737,
        '%' => 0.7393,
        '&' => 0.6577,
        '\'' => 0.1611,
        '(' => 0.3496,
        ')' => 0.3511,
        '*' => 0.4541,
        '+' => 0.5449,
        ',' => 0.2461,
        '-' => 0.394,
        '.' => 0.29,
        '/' => 0.3711,
        '0' => 0.5737,
        '1' => 0.5737,
        '2' => 0.5737,
        '3' => 0.5737,
        '4' => 0.5737,
        '5' => 0.5737,
        '6' => 0.5737,
        '7' => 0.5737,
        '8' => 0.5737,
        '9' => 0.5737,
        ':' => 0.2817,
        ';' => 0.2632,
        '<' => 0.5098,
        '=' => 0.5737,
        '>' => 0.5161,
        '?' => 0.4976,
        '@' => 0.8965,
        'A' => 0.6719,
        'B' => 0.6382,
        'C' => 0.6543,
        'D' => 0.6499,
        'E' => 0.5625,
        'F' => 0.5488,
        'G' => 0.6816,
        'H' => 0.7061,
        'I' => 0.2925,
        'J' => 0.5586,
        'K' => 0.6357,
        'L' => 0.541,
        'M' => 0.8755,
        'N' => 0.7056,
        'O' => 0.6895,
        'P' => 0.6445,
        'Q' => 0.6895,
        'R' => 0.6406,
        'S' => 0.6157,
        'T' => 0.6196,
        'U' => 0.6592,
        'V' => 0.6533,
        'W' => 0.875,
        'X' => 0.6348,
        'Y' => 0.6191,
        'Z' => 0.6069,
        '[' => 0.2769,
        '\\' => 0.4214,
        ']' => 0.2769,
        '^' => 0.438,
        '_' => 0.4448,
        '`' => 0.3301,
        'a' => 0.5352,
        'b' => 0.5625,
        'c' => 0.521,
        'd' => 0.563,
        'e' => 0.54,
        'f' => 0.3579,
        'g' => 0.5713,
        'h' => 0.5601,
        'i' => 0.2646,
        'j' => 0.2603,
        'k' => 0.5342,
        'l' => 0.2646,
        'm' => 0.8662,
        'n' => 0.5605,
        'o' => 0.5645,
        'p' => 0.5625,
        'q' => 0.564,
        'r' => 0.3662,
        's' => 0.5137,
        't' => 0.3384,
        'u' => 0.5601,
        'v' => 0.5063,
        'w' => 0.7344,
        'x' => 0.5088,
        'y' => 0.5034,
        'z' => 0.5088,
        '{' => 0.3296,
        '|' => 0.252,
        '}' => 0.3296,
        '~' => 0.6479,
        'à' => 0.5352,
        'â' => 0.5352,
        'ä' => 0.5352,
        'é' => 0.54,
        'è' => 0.54,
        'ê' => 0.54,
        'ë' => 0.54,
        'î' => 0.2729,
        'ï' => 0.2729,
        'ô' => 0.5645,
        'ö' => 0.5645,
        'ù' => 0.5601,
        'û' => 0.5601,
        'ü' => 0.5601,
        'ç' => 0.521,
        'À' => 0.6719,
        'Â' => 0.6719,
        'Ä' => 0.6719,
        'É' => 0.5625,
        'È' => 0.5625,
        'Ê' => 0.5625,
        'Ë' => 0.5625,
        'Î' => 0.2925,
        'Ï' => 0.2925,
        'Ô' => 0.6895,
        'Ö' => 0.6895,
        'Ù' => 0.6592,
        'Û' => 0.6592,
        'Ü' => 0.6592,
        'Ç' => 0.6543,
        '—' => 0.7617,
        '–' => 0.6304,
        '’' => 0.2295,
        '‘' => 0.2334,
        '“' => 0.4033,
        '”' => 0.4067,
        '•' => 0.3594,
        '…' => 0.7407,
    ];

    public static function lineHeight(float $fontSize): float
    {
        return $fontSize * self::LINE_HEIGHT_RATIO;
    }

    public static function width(string $text, float $fontSize, float $letterSpacing = 0.0, bool $bold = false): float
    {
        $table = $bold ? self::WIDTHS_BOLD : self::WIDTHS_REGULAR;
        $chars = mb_str_split($text);
        $total = 0.0;
        foreach ($chars as $char) {
            $total += $table[$char] ?? self::FALLBACK_WIDTH;
        }

        // Android's Paint.letterSpacing is an em-per-character value added
        // between glyphs — matching that here keeps tracked/uppercase
        // caption labels (e.g. a small-caps section header) from
        // under-measuring and wrapping too early.
        return $total * $fontSize + count($chars) * $letterSpacing * $fontSize;
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
    public static function wrap(string $text, float $fontSize, float $maxWidth, float $letterSpacing = 0.0, bool $bold = false): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        if ($words === ['']) {
            return [''];
        }

        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if ($current !== '' && self::width($candidate, $fontSize, $letterSpacing, $bold) > $maxWidth) {
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
