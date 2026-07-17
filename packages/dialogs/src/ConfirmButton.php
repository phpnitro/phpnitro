<?php

namespace Engine\Dialogs;

use Engine\Widget;

/**
 * Shows a confirmation dialog; only submits `action` (via phpxNav, no full
 * page reload) if the user confirms — same idiom as VibrateButton/
 * NotifyButton (a plain button calling a JS bridge), not a <form> submit,
 * since the server call must not happen until the user actually confirms.
 */
final class ConfirmButton extends Widget
{
    private const DEFAULT_CLASSES = 'bg-red-600 text-white font-medium px-4 py-2 rounded-lg';

    public function __construct(
        private readonly string $message,
        private readonly string $action,
        private readonly string $label = 'Confirmer',
        private readonly string $title = '',
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    public static function make(
        string $message,
        string $action,
        string $label = 'Confirmer',
        string $title = '',
        string $classes = self::DEFAULT_CLASSES,
    ): self {
        return new self($message, $action, $label, $title, $classes);
    }

    public function render(): string
    {
        $message = htmlspecialchars(json_encode($this->message, JSON_THROW_ON_ERROR), ENT_QUOTES);
        $title = htmlspecialchars(json_encode($this->title, JSON_THROW_ON_ERROR), ENT_QUOTES);
        $action = htmlspecialchars(json_encode($this->action, JSON_THROW_ON_ERROR), ENT_QUOTES);

        return sprintf(
            '<button type="button" '
            . 'onclick="phpxDialogs.confirm(%s, %s, function () { window.phpxNav.submitAction(%s); })" '
            . 'class="%s">%s</button>',
            $message,
            $title,
            $action,
            htmlspecialchars($this->classes, ENT_QUOTES),
            htmlspecialchars($this->label, ENT_QUOTES),
        );
    }
}
