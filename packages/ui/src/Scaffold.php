<?php

namespace Engine;

/**
 * Standard screen structure: optional fixed AppBar on top, scrollable body
 * with the right paddings, FAB and Drawer. The bottom nav itself is no
 * longer rendered here — it's a single persistent widget PageRenderer
 * places once outside every screen's own tree (see Screen::showsBottomNav())
 * — $hasBottomNav only tells this Scaffold whether to reserve room for it
 * (pb-24 vs pb-4) so content doesn't render underneath the fixed bar.
 */
final class Scaffold extends Widget
{
    public function __construct(
        private readonly Widget $body,
        private readonly ?Widget $appBar = null,
        private readonly bool $hasBottomNav = false,
        private readonly ?Widget $floatingActionButton = null,
        private readonly ?Widget $drawer = null,
    ) {
    }

    public static function make(
        Widget $body,
        ?Widget $appBar = null,
        bool $hasBottomNav = false,
        ?Widget $floatingActionButton = null,
        ?Widget $drawer = null,
    ): self {
        return new self($body, $appBar, $hasBottomNav, $floatingActionButton, $drawer);
    }

    public function render(): string
    {
        $top = $this->appBar !== null ? 'pt-18' : 'pt-4';
        $bottom = $this->hasBottomNav ? 'pb-24' : 'pb-4';

        return ($this->drawer?->render() ?? '')
            . ($this->appBar?->render() ?? '')
            . "<main class=\"{$top} {$bottom} px-4 max-w-lg mx-auto w-full\">"
            . $this->body->render()
            . '</main>'
            . ($this->floatingActionButton?->render() ?? '');
    }
}
