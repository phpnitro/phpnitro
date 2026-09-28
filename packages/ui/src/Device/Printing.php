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
 * Hands the CURRENT screen's own draw commands to the system print
 * dialog (NativeRenderPocActivity.kt's own printCurrentScreen() —
 * android.print.PrintManager on Android, UIPrintInteractionController
 * on iOS) — real, same-fidelity vector output there, a rasterized
 * snapshot on iOS (see NativeScreenViewController.swift's own
 * printCurrentScreen() docblock for why: no CGContext-drawing entry
 * point into UIPrintInteractionController). "Save to PDF" is one of the
 * system print dialog's own destinations on both platforms — this is
 * how a screen becomes a real, shareable PDF (an order receipt, an
 * invoice, ...) without a bespoke PDF-generation library.
 *
 * Found missing testing the ecommerce example app's own order receipt:
 * "printpdf" was already a real, working action string on both
 * platforms, just never wrapped behind a `Engine\Device\*` class the
 * way every other device capability here is — a project had no way to
 * discover or attach it to a Button without reading this framework's
 * own native source first.
 */
final class Printing
{
    public static function printAction(): string
    {
        return 'device:printpdf';
    }
}
