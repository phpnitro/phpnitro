<?php

namespace Engine;

/**
 * Triggers a real system notification via native NotificationCompat (see
 * WebAppInterface.showNotification) — works fully offline, no Firebase or
 * network call needed. This is a *local* notification; waking the app from
 * a remote server still needs FCM (see android/.../FcmService.kt.example).
 */
final class NotifyButton extends Widget
{
    private const DEFAULT_CLASSES = 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 '
        . 'font-medium px-4 py-2 rounded-lg';

    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly string $label = 'Notifier',
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    public static function make(
        string $title,
        string $message,
        string $label = 'Notifier',
        string $classes = self::DEFAULT_CLASSES,
    ): self {
        return new self($title, $message, $label, $classes);
    }

    public function render(): string
    {
        return sprintf(
            '<button type="button" onclick="phpxDevice.notify(%s, %s)" class="%s">%s</button>',
            htmlspecialchars(json_encode($this->title), ENT_QUOTES),
            htmlspecialchars(json_encode($this->message), ENT_QUOTES),
            htmlspecialchars($this->classes, ENT_QUOTES),
            htmlspecialchars($this->label, ENT_QUOTES),
        );
    }
}
