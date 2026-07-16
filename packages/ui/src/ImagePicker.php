<?php

namespace Engine;

/**
 * Native image picker (system gallery/file app) with a live preview. The
 * picked image ends up as a data: URL in a hidden field named $fieldName,
 * so it submits as part of a normal Form POST — see UploadController on
 * the backend side for how to decode + persist it.
 */
final class ImagePicker extends Widget
{
    private const DEFAULT_CLASSES = 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 '
        . 'font-medium px-4 py-2 rounded-lg';

    public function __construct(
        private readonly string $fieldName,
        private readonly string $label = 'Choisir une image',
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    public static function make(
        string $fieldName,
        string $label = 'Choisir une image',
        string $classes = self::DEFAULT_CLASSES,
    ): self {
        return new self($fieldName, $label, $classes);
    }

    public function render(): string
    {
        $fieldName = htmlspecialchars($this->fieldName, ENT_QUOTES);
        $hiddenId = 'imgpicker_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $this->fieldName);
        $previewId = $hiddenId . '_preview';
        $classes = htmlspecialchars($this->classes, ENT_QUOTES);

        return sprintf(
            '<div class="flex flex-col gap-2">'
            . '<input type="hidden" name="%s" id="%s">'
            . '<button type="button" onclick="phpxDevice.pickImage(\'%s\', \'%s\')" class="%s">%s</button>'
            . '<img id="%s" class="w-full max-w-xs rounded-lg" alt="Image sélectionnée">'
            . '</div>',
            $fieldName,
            $hiddenId,
            $previewId,
            $hiddenId,
            $classes,
            htmlspecialchars($this->label, ENT_QUOTES),
            $previewId,
        );
    }
}
