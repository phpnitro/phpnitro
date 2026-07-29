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
 * The native-tree equivalent of Engine\Table — equal-width columns (a
 * Canvas has no intrinsic-column-width algorithm to fall back on, so this
 * doesn't attempt one), an optional bold header row, and a divider between
 * every row. String cells only: a Widget-cell isn't meaningful here since
 * there's no shared RenderNode/Widget interface to accept one of either.
 */
final class NativeTable implements RenderNode
{
    private readonly RenderNode $content;

    /**
     * @param array<int, array<int, string>> $rows
     * @param array<int, string> $headers
     */
    public function __construct(array $rows, array $headers = [])
    {
        $lines = [];

        if ($headers !== []) {
            $lines[] = $this->row($headers, bold: true);
            $lines[] = new NativeDivider();
        }

        foreach ($rows as $index => $row) {
            if ($index > 0) {
                $lines[] = new NativeDivider();
            }
            $lines[] = $this->row($row, bold: false);
        }

        $this->content = RenderFlex::column($lines, crossAxisAlignment: CrossAxisAlignment::STRETCH);
    }

    /**
     * @param array<int, string> $cells
     */
    private function row(array $cells, bool $bold): RenderNode
    {
        return new RenderPadding(
            EdgeInsets::symmetric(vertical: Tokens::SPACE_SM),
            RenderFlex::row(array_map(
                static fn (string $cell): RenderNode => new Flexible(
                    new RenderText($cell, Tokens::TEXT_BODY_SMALL, Tokens::ink()->toHex(), bold: $bold),
                ),
                $cells,
            )),
        );
    }

    public function layout(Constraints $constraints): Size
    {
        return $this->content->layout($constraints);
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        $this->content->paint($canvas, $x, $y);
    }
}
