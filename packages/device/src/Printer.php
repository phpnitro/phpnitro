<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Device;

/**
 * Turns the current page into a PDF via Android's native print pipeline
 * (WebView.createPrintDocumentAdapter + PrintManager — the system "Save as
 * PDF" flow) — no PHP PDF library needed. A JS trigger, not a widget —
 * attach it to any button via Button::make($label, onClick: Printer::onClick()).
 * Named Printer, not Print — `print` is a reserved word in PHP and can't
 * be used as a class name.
 */
final class Printer
{
    public static function onClick(): string
    {
        return 'phpxDevice.print()';
    }
}
