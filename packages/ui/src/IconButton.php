<?php

namespace Engine;

/**
 * A button showing only an icon (see Icon::* for the built-in set) —
 * same action/no-action behavior as Button, just icon content instead of
 * a text label.
 */
final class IconButton extends Widget
{
    private const DEFAULT_CLASSES = 'p-2 rounded-full text-gray-600 dark:text-gray-300 '
        . 'hover:bg-gray-100 dark:hover:bg-gray-700';

    public function __construct(
        private readonly string $icon,
        private readonly ?string $action = null,
        private readonly string $classes = self::DEFAULT_CLASSES,
        private readonly string $ariaLabel = '',
    ) {
    }

    public static function make(
        string $icon,
        ?string $action = null,
        string $classes = self::DEFAULT_CLASSES,
        string $ariaLabel = '',
    ): self {
        return new self($icon, $action, $classes, $ariaLabel);
    }

    public function render(): string
    {
        $classes = htmlspecialchars($this->classes, ENT_QUOTES);
        $aria = $this->ariaLabel !== '' ? ' aria-label="' . htmlspecialchars($this->ariaLabel, ENT_QUOTES) . '"' : '';

        if ($this->action === null) {
            return "<button type=\"button\" class=\"{$classes}\"{$aria}>{$this->icon}</button>";
        }

        $action = htmlspecialchars($this->action, ENT_QUOTES);

        return '<form method="post" class="inline">'
            . "<input type=\"hidden\" name=\"_action\" value=\"{$action}\">" . Csrf::field()
            . "<button type=\"submit\" class=\"{$classes}\"{$aria}>{$this->icon}</button>"
            . '</form>';
    }
}
