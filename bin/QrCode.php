<?php

/**
 * Minimal, dependency-free QR code encoder — Byte mode only, error
 * correction level L, versions 1-5 (single Reed-Solomon block, no
 * version-information block needed since that only applies to version 7+).
 * That's the entire footprint this needs: encoding a LAN URL like
 * "http://192.168.1.23:8090", nothing more general.
 *
 * No third-party dependency, no external binary — works identically
 * wherever PHP runs (Linux/macOS/Windows), which a phpx CLI feature needs.
 */
final class QrCode
{
    // [totalCodewords, ecCodewordsPerBlock, dataCodewords] per version (index 0 = version 1), EC level L, single block.
    private const VERSION_PARAMS = [
        1 => [26, 7, 19],
        2 => [44, 10, 34],
        3 => [70, 15, 55],
        4 => [100, 20, 80],
        5 => [134, 26, 108],
    ];

    // Alignment pattern center coordinate (the non-6 one) per version; version 1 has none.
    private const ALIGNMENT_COORD = [2 => 18, 3 => 22, 4 => 26, 5 => 30];

    /** @return bool[][] true = dark module */
    public static function encode(string $text): array
    {
        $version = self::pickVersion(strlen($text));
        [$totalCodewords, $ecCount, $dataCount] = self::VERSION_PARAMS[$version];

        $dataCodewords = self::buildDataCodewords($text, $dataCount);
        $ecCodewords = self::reedSolomon($dataCodewords, $ecCount);
        $allCodewords = array_merge($dataCodewords, $ecCodewords);

        $size = 4 * $version + 17;
        [$matrix, $isFunction] = self::buildFunctionPatterns($version, $size);

        self::placeData($matrix, $isFunction, $size, $allCodewords);
        self::applyMask($matrix, $isFunction, $size);
        self::placeFormatInfo($matrix, $size);

        return $matrix;
    }

    /**
     * Unicode half-block rendering — each terminal character row packs TWO
     * module rows (▀ = top dark/bottom light, ▄ = top light/bottom dark,
     * █ = both dark, space = both light), the standard trick for a QR code
     * that doesn't take up an absurd amount of terminal real estate. A
     * 4-module quiet zone border is included (real scanners rely on it
     * being present to detect the code's edges).
     */
    public static function toTerminal(string $text): string
    {
        $matrix = self::encode($text);
        $size = count($matrix);
        $border = 4;
        $padded = array_fill(0, $size + 2 * $border, array_fill(0, $size + 2 * $border, false));
        foreach ($matrix as $r => $row) {
            foreach ($row as $c => $dark) {
                $padded[$r + $border][$c + $border] = $dark;
            }
        }

        $totalSize = $size + 2 * $border;
        $lines = [];
        for ($r = 0; $r < $totalSize; $r += 2) {
            $line = '';
            for ($c = 0; $c < $totalSize; $c++) {
                $top = $padded[$r][$c];
                $bottom = $padded[$r + 1][$c] ?? false;
                $line .= match (true) {
                    $top && $bottom => '█',
                    $top && !$bottom => '▀',
                    !$top && $bottom => '▄',
                    default => ' ',
                };
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Renders to a PNG file via GD — the terminal half-block rendering
     * (toTerminal()) depends on the terminal's font metrics giving perfectly
     * square, gap-free glyphs, which not every terminal emulator does, and a
     * blurry/uneven render just won't scan. A PNG served over HTTP and
     * opened in a browser has no such font-rendering variable.
     */
    public static function toPngFile(string $text, string $path, int $scale = 10): void
    {
        $matrix = self::encode($text);
        $size = count($matrix);
        $border = 4;
        $totalModules = $size + 2 * $border;
        $pixels = $totalModules * $scale;

        $image = imagecreatetruecolor($pixels, $pixels);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $pixels - 1, $pixels - 1, $white);

        foreach ($matrix as $r => $row) {
            foreach ($row as $c => $dark) {
                if (!$dark) {
                    continue;
                }
                $x = ($c + $border) * $scale;
                $y = ($r + $border) * $scale;
                imagefilledrectangle($image, $x, $y, $x + $scale - 1, $y + $scale - 1, $black);
            }
        }

        imagepng($image, $path);
        imagedestroy($image);
    }

    private static function pickVersion(int $textLength): int
    {
        foreach (self::VERSION_PARAMS as $version => [, , $dataCount]) {
            // -2 bytes reserved for the mode+length header (byte mode,
            // 8-bit count indicator for versions 1-9 — see buildDataCodewords()).
            if ($textLength <= $dataCount - 2) {
                return $version;
            }
        }

        throw new \InvalidArgumentException('Text too long for this encoder (max ~106 bytes)');
    }

    /** @return int[] one byte value per array element */
    private static function buildDataCodewords(string $text, int $dataCount): array
    {
        $bits = '0100'; // byte mode
        $bits .= str_pad(decbin(strlen($text)), 8, '0', STR_PAD_LEFT); // count indicator, 8 bits (versions 1-9)
        foreach (str_split($text) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $capacityBits = $dataCount * 8;
        $bits .= str_repeat('0', min(4, $capacityBits - strlen($bits))); // terminator
        $bits = str_pad($bits, (int) ceil(strlen($bits) / 8) * 8, '0'); // byte-align

        $codewords = array_map('bindec', str_split($bits, 8));

        $padBytes = [0xEC, 0x11];
        $i = 0;
        while (count($codewords) < $dataCount) {
            $codewords[] = $padBytes[$i % 2];
            $i++;
        }

        return $codewords;
    }

    /** @param int[] $data @return int[] EC codewords */
    private static function reedSolomon(array $data, int $ecCount): array
    {
        [$expTable, $logTable] = self::gf256Tables();

        $gfMul = static function (int $a, int $b) use ($expTable, $logTable): int {
            if ($a === 0 || $b === 0) {
                return 0;
            }

            return $expTable[($logTable[$a] + $logTable[$b]) % 255];
        };

        // Generator polynomial: product of (x - 2^i) for i = 0..ecCount-1.
        $generator = [1];
        for ($i = 0; $i < $ecCount; $i++) {
            $next = array_fill(0, count($generator) + 1, 0);
            foreach ($generator as $j => $coefficient) {
                $next[$j] ^= $gfMul($coefficient, $expTable[$i]);
                $next[$j + 1] ^= $coefficient;
            }
            $generator = $next;
        }
        // Built above in ascending-degree order (index 0 = constant term) —
        // the division loop below needs descending order (index 0 =
        // highest-degree term) to align with how $remainder is indexed.
        $generator = array_reverse($generator);

        $remainder = array_merge($data, array_fill(0, $ecCount, 0));
        for ($i = 0; $i < count($data); $i++) {
            $factor = $remainder[$i];
            if ($factor === 0) {
                continue;
            }
            for ($j = 0; $j < count($generator); $j++) {
                $remainder[$i + $j] ^= $gfMul($generator[$j], $factor);
            }
        }

        return array_slice($remainder, count($data));
    }

    /** @return array{0: int[], 1: int[]} [expTable, logTable] for GF(256), primitive polynomial 0x11D */
    private static function gf256Tables(): array
    {
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $value = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $value;
            $log[$value] = $i;
            $value <<= 1;
            if ($value & 0x100) {
                $value ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }

        return [$exp, $log];
    }

    /**
     * @return array{0: bool[][], 1: bool[][]} [matrix (initial module values), isFunction (true = never touched by data/mask)]
     */
    private static function buildFunctionPatterns(int $version, int $size): array
    {
        $matrix = array_fill(0, $size, array_fill(0, $size, false));
        $isFunction = array_fill(0, $size, array_fill(0, $size, false));

        $setFn = static function (int $row, int $col, bool $dark) use (&$matrix, &$isFunction): void {
            $matrix[$row][$col] = $dark;
            $isFunction[$row][$col] = true;
        };

        $drawFinder = static function (int $topRow, int $topCol) use (&$matrix, &$isFunction, $size, $setFn): void {
            for ($dr = -1; $dr <= 7; $dr++) {
                for ($dc = -1; $dc <= 7; $dc++) {
                    $r = $topRow + $dr;
                    $c = $topCol + $dc;
                    if ($r < 0 || $r >= $size || $c < 0 || $c >= $size) {
                        continue;
                    }
                    $isBorder = $dr === -1 || $dr === 7 || $dc === -1 || $dc === 7;
                    $isRing = $dr >= 0 && $dr <= 6 && $dc >= 0 && $dc <= 6 && ($dr === 0 || $dr === 6 || $dc === 0 || $dc === 6);
                    $isCore = $dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4;
                    $dark = ($isRing || $isCore) && !$isBorder;
                    $setFn($r, $c, $dark);
                }
            }
        };

        $drawFinder(0, 0);
        $drawFinder(0, $size - 7);
        $drawFinder($size - 7, 0);

        // Timing patterns (row 6 / column 6), alternating, skipping finder zones.
        for ($i = 8; $i < $size - 8; $i++) {
            $dark = $i % 2 === 0;
            $setFn(6, $i, $dark);
            $setFn($i, 6, $dark);
        }

        // Alignment pattern (versions 2-5 here: exactly one, at (coord, coord)).
        if (isset(self::ALIGNMENT_COORD[$version])) {
            $center = self::ALIGNMENT_COORD[$version];
            for ($dr = -2; $dr <= 2; $dr++) {
                for ($dc = -2; $dc <= 2; $dc++) {
                    $ring = max(abs($dr), abs($dc));
                    $setFn($center + $dr, $center + $dc, $ring !== 1);
                }
            }
        }

        // Dark module, always on.
        $setFn(4 * $version + 9, 8, true);

        // Reserve format info strips (values filled in later by placeFormatInfo()).
        for ($i = 0; $i <= 8; $i++) {
            if ($i !== 6) {
                $setFn(8, $i, false);
                $setFn($i, 8, false);
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $setFn(8, $size - 1 - $i, false);
            $setFn($size - 1 - $i, 8, false);
        }
        $setFn(8, 8, false);

        return [$matrix, $isFunction];
    }

    /** @param bool[][] $matrix @param bool[][] $isFunction @param int[] $codewords */
    private static function placeData(array &$matrix, array $isFunction, int $size, array $codewords): void
    {
        $bits = '';
        foreach ($codewords as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        $bitIndex = 0;
        $bitCount = strlen($bits);
        $upward = true;
        $col = $size - 1;

        while ($col > 0) {
            if ($col === 6) {
                $col--; // column 6 is the vertical timing pattern, skip entirely
            }
            $rows = $upward ? range($size - 1, 0) : range(0, $size - 1);
            foreach ($rows as $row) {
                foreach ([$col, $col - 1] as $c) {
                    if ($isFunction[$row][$c]) {
                        continue;
                    }
                    if ($bitIndex < $bitCount) {
                        $matrix[$row][$c] = $bits[$bitIndex] === '1';
                        $bitIndex++;
                    }
                }
            }
            $col -= 2;
            $upward = !$upward;
        }
    }

    /** @param bool[][] $matrix @param bool[][] $isFunction */
    private static function applyMask(array &$matrix, array $isFunction, int $size): void
    {
        // Mask 0: (row + col) % 2 == 0 — fixed choice, not penalty-scored
        // (this is a low-stakes dev-convenience QR code, not competing for
        // best-case scan reliability in adverse lighting; any of the 8
        // masks is equally VALID, this one just needs to match the format
        // info written in placeFormatInfo()).
        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size; $col++) {
                if ($isFunction[$row][$col]) {
                    continue;
                }
                if (($row + $col) % 2 === 0) {
                    $matrix[$row][$col] = !$matrix[$row][$col];
                }
            }
        }
    }

    private static function placeFormatInfo(array &$matrix, int $size): void
    {
        // EC level L = 01, mask pattern 0 = 000.
        $data = 0b01000;
        $rem = $data << 10;
        for ($i = 14; $i >= 10; $i--) {
            if (($rem >> $i) & 1) {
                $rem ^= 0b10100110111 << ($i - 10);
            }
        }
        $bits = ($data << 10) | $rem;
        $bits ^= 0b101010000010010; // fixed XOR mask

        $get = static fn (int $i): bool => (($bits >> $i) & 1) === 1;

        // Around top-left finder.
        for ($i = 0; $i <= 5; $i++) {
            $matrix[8][$i] = $get($i);
        }
        $matrix[8][7] = $get(6);
        $matrix[8][8] = $get(7);
        $matrix[7][8] = $get(8);
        for ($i = 9; $i <= 14; $i++) {
            $matrix[14 - $i][8] = $get($i);
        }

        // Top-right + bottom-left copies.
        for ($i = 0; $i <= 7; $i++) {
            $matrix[8][$size - 1 - $i] = $get($i);
        }
        for ($i = 8; $i <= 14; $i++) {
            $matrix[$size - 15 + $i][8] = $get($i);
        }
    }
}
