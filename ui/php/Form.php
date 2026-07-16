<?php

namespace Engine;

/**
 * Groups input widgets and a submit button into one <form> posting a named
 * action. The screen receives every input value (by input name) as the
 * $data array of its onXxx(array $data) handler.
 */
final class Form extends Widget
{
    /**
     * @param Widget[] $children
     */
    public function __construct(
        private readonly array $children,
        private readonly string $action,
        private readonly string $classes = 'flex flex-col gap-3',
    ) {
    }

    /**
     * @param Widget[] $children
     */
    public static function make(array $children, string $action, string $classes = 'flex flex-col gap-3'): self
    {
        return new self($children, $action, $classes);
    }

    public function render(): string
    {
        $inner = implode('', array_map(static fn (Widget $child) => $child->render(), $this->children));

        return sprintf(
            '<form method="post" class="%s">'
            . '<input type="hidden" name="_action" value="%s">%s%s</form>',
            htmlspecialchars($this->classes, ENT_QUOTES),
            htmlspecialchars($this->action, ENT_QUOTES),
            Csrf::field(),
            $inner,
        );
    }
}
