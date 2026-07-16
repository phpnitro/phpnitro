<?php

namespace Engine;

/**
 * Turns the current page into a PDF via Android's native print pipeline
 * (WebView.createPrintDocumentAdapter + PrintManager — the system "Save as
 * PDF" flow) — no PHP PDF library needed.
 */
final class PrintButton extends Widget
{
    private const DEFAULT_CLASSES = 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 '
        . 'font-medium px-4 py-2 rounded-lg';

    public function __construct(
        private readonly string $label = 'Imprimer / PDF',
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    public static function make(string $label = 'Imprimer / PDF', string $classes = self::DEFAULT_CLASSES): self
    {
        return new self($label, $classes);
    }

    public function render(): string
    {
        return sprintf(
            '<button type="button" onclick="phpxDevice.print()" class="%s">%s</button>',
            htmlspecialchars($this->classes, ENT_QUOTES),
            htmlspecialchars($this->label, ENT_QUOTES),
        );
    }
}
