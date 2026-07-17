<?php

namespace Engine\Dialogs;

use Engine\Widget;

final class AlertButton extends Widget
{
    private const DEFAULT_CLASSES = 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 '
        . 'font-medium px-4 py-2 rounded-lg';

    public function __construct(
        private readonly string $message,
        private readonly string $label = 'Afficher un message',
        private readonly string $title = '',
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    public static function make(
        string $message,
        string $label = 'Afficher un message',
        string $title = '',
        string $classes = self::DEFAULT_CLASSES,
    ): self {
        return new self($message, $label, $title, $classes);
    }

    public function render(): string
    {
        $message = htmlspecialchars(json_encode($this->message, JSON_THROW_ON_ERROR), ENT_QUOTES);
        $title = htmlspecialchars(json_encode($this->title, JSON_THROW_ON_ERROR), ENT_QUOTES);

        return sprintf(
            '<button type="button" onclick="phpxDialogs.alert(%s, %s)" class="%s">%s</button>',
            $message,
            $title,
            htmlspecialchars($this->classes, ENT_QUOTES),
            htmlspecialchars($this->label, ENT_QUOTES),
        );
    }
}
