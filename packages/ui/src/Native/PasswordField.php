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
 * TextField(obscure: true) plus a real reveal/hide eye toggle — the one
 * thing plain TextField couldn't do on its own, since "obscure" there is
 * a static render-time flag with nothing to flip. The toggle reuses
 * Checkbox/Toggle/RadioGroup's exact "toggle:" dispatch (meta.next
 * becomes $_GET["{$name}_reveal"]) rather than anything new — a real,
 * ordinary server round-trip, same as every other stateful widget here.
 *
 * Known limitation, worth knowing before reaching for this: the toggle
 * only affects how the field renders BEFORE it's tapped (its static
 * pre-focus box). While the real android.widget.EditText overlay is
 * actively focused (see TextField's own docblock/showTextInput()),
 * tapping the eye does nothing to what's already on screen — the
 * keyboard's own EditText owns its InputType for the duration of that
 * focus session, and changing it live isn't wired up (would need a new
 * Kotlin-side action, not just a PHP-level field flip). Tap away to
 * commit the field, THEN toggle reveal to check what was typed — a real
 * constraint, not a bug, until that Kotlin path gets built.
 *
 * The eye icon is overlaid via Stack+Positioned, which is why this can't
 * just be TextField($obscure: true) plus an icon appended after it in a
 * Row — Stack.layout() always hands non-Positioned children a LOOSENED
 * constraint (min forced to 0, max preserved), so an ordinary TextField
 * relying on a STRETCH-aligned Flex ancestor for its width would shrink
 * to its own placeholder's content width instead of filling the row.
 * Passing an explicit resolved $width straight into TextField (see its
 * own docblock for that parameter) sidesteps the whole problem.
 */
final class PasswordField implements Widget
{
    private ?Stack $content = null;

    public function __construct(
        private readonly string $name,
        private readonly string $value = '',
        private readonly string $placeholder = '',
        private readonly ?float $width = null,
        private readonly float $height = 52.0,
    ) {
    }

    public function layout(Constraints $constraints): Size
    {
        $resolvedWidth = $this->width ?? ($constraints->hasBoundedWidth() ? $constraints->maxWidth : 300.0);
        $revealed = ($_GET["{$this->name}_reveal"] ?? '') === '1';

        $field = new TextField($this->name, $this->value, $this->placeholder, obscure: !$revealed, height: $this->height, width: $resolvedWidth);
        $eyeButton = new Tappable(
            new Icon($revealed ? 'visibility_off' : 'visibility', 20.0, Tokens::inkMuted()->toHex()),
            "toggle:{$this->name}_reveal",
            ['next' => $revealed ? '' : '1'],
        );

        $this->content = new Stack([
            $field,
            new Positioned($eyeButton, top: $this->height / 2 - 10.0, right: Tokens::SPACE_MD),
        ]);

        return $this->content->layout(new Constraints($resolvedWidth, $resolvedWidth, $this->height, $this->height));
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $this->content?->paint($canvas, $x, $y);
    }
}
