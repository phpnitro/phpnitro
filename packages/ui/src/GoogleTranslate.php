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

/**
 * Embeds Google's own Website Translator widget (the standard
 * translate.google.com/translate_a/element.js embed) — a dropdown that
 * translates the rendered page client-side. Requires network access,
 * which the WebView already has (INTERNET permission).
 */
final class GoogleTranslate extends Widget
{
    public function __construct(
        private readonly string $pageLanguage = 'fr',
        private readonly string $includedLanguages = 'fr,en,es,pt,ar',
    ) {
    }

    public static function make(string $pageLanguage = 'fr', string $includedLanguages = 'fr,en,es,pt,ar'): self
    {
        return new self($pageLanguage, $includedLanguages);
    }

    public function render(): string
    {
        $pageLanguage = htmlspecialchars($this->pageLanguage, ENT_QUOTES);
        $includedLanguages = htmlspecialchars($this->includedLanguages, ENT_QUOTES);

        return <<<HTML
            <div id="google_translate_element"></div>
            <script>
                function googleTranslateElementInit() {
                    new google.translate.TranslateElement({
                        pageLanguage: '{$pageLanguage}',
                        includedLanguages: '{$includedLanguages}',
                        layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
                    }, 'google_translate_element');
                }
            </script>
            <script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
            HTML;
    }
}
