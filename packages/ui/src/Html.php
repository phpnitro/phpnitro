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
 * Passthrough for pre-built HTML/JS fragments (script tags, output
 * elements) that don't belong to a specific button — the escape hatch a
 * service class needs to compose script/element snippets into a widget
 * tree without being a "widget" that renders its own opinionated markup.
 * Escaping is the caller's responsibility, same as any service class that
 * already builds its own escaped HTML/JS (see Engine\Device\, Engine\Payments\).
 */
final class Html extends Widget
{
    public function __construct(private readonly string $html)
    {
    }

    public static function raw(string $html): self
    {
        return new self($html);
    }

    public function render(): string
    {
        return $this->html;
    }
}
