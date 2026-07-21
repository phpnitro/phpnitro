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

final class Button extends Widget
{
    use RendersAction;

    private const DEFAULT_CLASSES = 'bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg transition-colors';

    public function __construct(
        private readonly string $label,
        private readonly ?string $action = null,
        private readonly string $classes = self::DEFAULT_CLASSES,
        private readonly ?string $onClick = null,
    ) {
    }

    public static function make(
        string $label,
        ?string $action = null,
        string $classes = self::DEFAULT_CLASSES,
        ?string $onClick = null,
    ): self {
        return new self($label, $action, $classes, $onClick);
    }

    public function render(): string
    {
        return $this->renderActionableButton($this->label, $this->action, $this->classes, $this->onClick);
    }
}
