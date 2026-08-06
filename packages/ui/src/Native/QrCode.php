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

use chillerlan\QRCode\QRCode as ChillerlanQrCode;

/**
 * Generates and draws a real QR code — the encoding itself (Reed-Solomon
 * error correction, version/mask selection) via chillerlan/php-qrcode
 * (already Composer-installed, same "reuse a real dependency instead of
 * hand-rolling error-correction math" call this framework already made
 * for Doctrine DBAL), painted as ordinary Canvas rects, one per dark
 * module — no PNG asset, no Kotlin change, entirely within the existing
 * draw-command pipeline. The counterpart to QrScanner (Engine\Device),
 * which reads a code back; this one produces one.
 */
final class QrCode implements Widget
{
    /** @var array<int, array<int, bool>> */
    private readonly array $matrix;

    private readonly int $moduleCount;

    private Size $size;

    public function __construct(
        string $data,
        private readonly float $boxSize = 200.0,
        private readonly string $foreground = '#000000',
        private readonly string $background = '#FFFFFF',
    ) {
        $qr = (new ChillerlanQrCode())->getQRMatrix($data);
        $this->matrix = $qr->getBooleanMatrix();
        $this->moduleCount = count($this->matrix);
        $this->size = Size::zero();
    }

    public function layout(Constraints $constraints): Size
    {
        $this->size = $constraints->constrain(new Size($this->boxSize, $this->boxSize));

        return $this->size;
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $canvas->rect($x, $y, $this->size->width, $this->size->height, $this->background);

        if ($this->moduleCount === 0) {
            return;
        }

        $moduleSize = $this->size->width / $this->moduleCount;
        foreach ($this->matrix as $row => $cols) {
            foreach ($cols as $col => $dark) {
                if ($dark) {
                    $canvas->rect($x + $col * $moduleSize, $y + $row * $moduleSize, $moduleSize, $moduleSize, $this->foreground);
                }
            }
        }
    }
}
