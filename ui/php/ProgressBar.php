<?php

namespace Engine;

/**
 * Linear progress bar. $value (0-100) is computed server-side — combine
 * with StreamBuilder to make it update live without a page reload (see
 * OrderConfirmationPage in examples/ecom for the same pattern applied to a
 * status label).
 */
final class ProgressBar extends Widget
{
    private const DEFAULT_CLASSES = 'w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden';

    public function __construct(
        private readonly float $value,
        private readonly string $classes = self::DEFAULT_CLASSES,
        private readonly string $barColor = 'bg-blue-600',
    ) {
    }

    public static function make(
        float $value,
        string $classes = self::DEFAULT_CLASSES,
        string $barColor = 'bg-blue-600',
    ): self {
        return new self($value, $classes, $barColor);
    }

    public function render(): string
    {
        $percent = max(0.0, min(100.0, $this->value));

        return sprintf(
            '<div class="%s" role="progressbar" aria-valuenow="%s" aria-valuemin="0" aria-valuemax="100">'
            . '<div class="h-full %s transition-all duration-300" style="width: %s%%"></div>'
            . '</div>',
            htmlspecialchars($this->classes, ENT_QUOTES),
            $percent,
            htmlspecialchars($this->barColor, ENT_QUOTES),
            $percent,
        );
    }
}
