<?php

namespace Engine;

/**
 * Standard screen structure: optional fixed AppBar on top, scrollable body
 * with the right paddings, optional BottomNavigation, FAB and Drawer.
 * Replaces the ad-hoc "centered column" layout with a real app skeleton.
 */
final class Scaffold extends Widget
{
    public function __construct(
        private readonly Widget $body,
        private readonly ?Widget $appBar = null,
        private readonly ?Widget $bottomNavigation = null,
        private readonly ?Widget $floatingActionButton = null,
        private readonly ?Widget $drawer = null,
    ) {
    }

    public static function make(
        Widget $body,
        ?Widget $appBar = null,
        ?Widget $bottomNavigation = null,
        ?Widget $floatingActionButton = null,
        ?Widget $drawer = null,
    ): self {
        return new self($body, $appBar, $bottomNavigation, $floatingActionButton, $drawer);
    }

    public function render(): string
    {
        $top = $this->appBar !== null ? 'pt-18' : 'pt-4';
        $bottom = $this->bottomNavigation !== null ? 'pb-24' : 'pb-4';

        return ($this->drawer?->render() ?? '')
            . ($this->appBar?->render() ?? '')
            . "<main class=\"{$top} {$bottom} px-4 max-w-lg mx-auto w-full\">"
            . $this->body->render()
            . '</main>'
            . ($this->floatingActionButton?->render() ?? '')
            . ($this->bottomNavigation?->render() ?? '');
    }
}
