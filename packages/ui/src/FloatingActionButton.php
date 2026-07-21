<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine;

final class FloatingActionButton extends Widget
{
    use RendersAction;

    private const DEFAULT_CLASSES = 'gpu-layer fixed bottom-20 right-4 w-14 h-14 rounded-full bg-blue-600 hover:bg-blue-700 '
        . 'text-white text-2xl leading-none flex items-center justify-center shadow-lg';

    public function __construct(
        private readonly string $label,
        private readonly ?string $action = null,
        private readonly string $classes = self::DEFAULT_CLASSES,
        private readonly string $ariaLabel = '',
    ) {
    }

    public static function make(
        string $label,
        ?string $action = null,
        string $classes = self::DEFAULT_CLASSES,
        string $ariaLabel = '',
    ): self {
        return new self($label, $action, $classes, $ariaLabel);
    }

    public function render(): string
    {
        // $label is usually a bare glyph ("+", "✎"...) — real text, but not
        // a meaningful accessible name on its own (confirmed with a real
        // TalkBack accessibility dump: the FAB was announced as literally
        // "plus"). $ariaLabel lets a caller supply what should actually be
        // spoken instead, same idiom as IconButton's $ariaLabel.
        return $this->renderActionableButton($this->label, $this->action, $this->classes, ariaLabel: $this->ariaLabel);
    }
}
