<?php

namespace Engine;

final class Button extends Widget
{
    private const DEFAULT_CLASSES = 'bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg transition-colors';

    public function __construct(
        private readonly string $label,
        private readonly ?string $action = null,
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    public static function make(string $label, ?string $action = null, string $classes = self::DEFAULT_CLASSES): self
    {
        return new self($label, $action, $classes);
    }

    public function render(): string
    {
        $classes = htmlspecialchars($this->classes, ENT_QUOTES);
        $label = htmlspecialchars($this->label, ENT_QUOTES);

        if ($this->action === null) {
            return sprintf('<button type="button" class="%s">%s</button>', $classes, $label);
        }

        $action = htmlspecialchars($this->action, ENT_QUOTES);

        return sprintf(
            '<form method="post" class="inline">'
            . '<input type="hidden" name="_action" value="%s">'
            . '<button type="submit" class="%s">%s</button>'
            . '</form>',
            $action,
            $classes,
            $label,
        );
    }
}
