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
 * A tappable grid of emoji — each cell reuses the exact "toggle:$name" +
 * meta.next mechanism PageView/NumberPicker already use (Android's own
 * Canvas.drawText renders emoji glyphs directly, same as any other text
 * command, no bitmap font needed). Tapping a cell REPLACES the field's
 * value with that one emoji, the same single-value round trip every
 * other stateful native widget here uses — it does not append to
 * existing text. A screen wanting "append this emoji to my message
 * draft" reads both the draft field and this one on submit and
 * concatenates them server-side.
 *
 * A curated set of commonly-used emoji, not the thousands-strong,
 * category-searchable dataset flutter_emoji_picker ships — this
 * framework has no bundled emoji metadata (names/keywords/skin-tone
 * variants) to search over yet. Pass your own $emoji list to pick a
 * different/larger set.
 */
final class EmojiPicker implements Widget
{
    private readonly Widget $content;

    public const DEFAULT_EMOJI = [
        '😀', '😂', '😍', '🥰', '😎', '🤔', '😢', '😡',
        '👍', '👎', '👏', '🙏', '💪', '🤝', '✌️', '👋',
        '❤️', '🔥', '🎉', '✨', '⭐', '💯', '✅', '❌',
        '🍕', '☕', '🎂', '⚽', '🎵', '📸', '🚗', '✈️',
    ];

    /**
     * @param array<int, string> $emoji
     */
    public function __construct(
        string $name,
        array $emoji = self::DEFAULT_EMOJI,
        int $columns = 8,
        float $cellSize = 40.0,
        float $scrollY = 0.0,
        float $viewportHeight = 240.0,
    ) {
        $this->content = new Grid(
            count($emoji),
            static fn (int $index): Widget => new Tappable(
                new Center(new Text($emoji[$index], 24.0)),
                "toggle:{$name}",
                ['next' => $emoji[$index]],
            ),
            $columns,
            $cellSize,
            $scrollY,
            $viewportHeight,
        );
    }

    public function layout(Constraints $constraints): Size
    {
        return $this->content->layout($constraints);
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $this->content->paint($canvas, $x, $y);
    }
}
