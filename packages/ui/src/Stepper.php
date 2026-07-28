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
 * Stateless — just draws the step header (done/current/upcoming), the body
 * for the current step, and Back/Next buttons. The actual state (which
 * step is active, data collected per step) belongs to the calling Screen's
 * $state, exactly like CheckoutPage accumulates validation state across
 * several POSTs — this widget has no session/state of its own.
 *
 * Unlike Form::make(), $body here should be the RAW field widgets for this
 * step (TextField, SelectBox...), not already wrapped in their own Form:
 * Stepper wraps the whole thing (body + Back/Next) in ONE <form> itself, so
 * both buttons submit whatever the user typed for this step. Back and Next
 * need to reach two DIFFERENT onXxx() actions from that single form, which
 * a shared hidden `_action` field can't do — so each button carries its own
 * `name="_action" value="..."` instead (only the clicked button's pair is
 * sent), same mechanism Screen::handle() already reads either way.
 */
final class Stepper extends Widget
{
    /**
     * @param string[] $stepLabels
     */
    public function __construct(
        private readonly int $currentStep,
        private readonly int $totalSteps,
        private readonly array $stepLabels,
        private readonly Widget $body,
        private readonly ?string $backAction = null,
        private readonly ?string $nextAction = null,
        private readonly string $backLabel = 'Retour',
        private readonly string $nextLabel = 'Suivant',
        private readonly ?Color $activeColor = null,
    ) {
    }

    /**
     * @param string[] $stepLabels
     */
    public static function make(
        int $currentStep,
        int $totalSteps,
        array $stepLabels,
        Widget $body,
        ?string $backAction = null,
        ?string $nextAction = null,
        string $backLabel = 'Retour',
        string $nextLabel = 'Suivant',
        ?Color $activeColor = null,
    ): self {
        return new self(
            $currentStep,
            $totalSteps,
            $stepLabels,
            $body,
            $backAction,
            $nextAction,
            $backLabel,
            $nextLabel,
            $activeColor,
        );
    }

    public function render(): string
    {
        return sprintf(
            '<div class="flex flex-col gap-4">%s<form method="post">%s%s%s</form></div>',
            $this->renderHeader(),
            Csrf::field(),
            $this->body->render(),
            $this->renderButtons(),
        );
    }

    private function renderHeader(): string
    {
        $items = '';

        for ($index = 0; $index < $this->totalSteps; $index++) {
            $label = $this->stepLabels[$index] ?? '';
            $state = match (true) {
                $index < $this->currentStep => 'done',
                $index === $this->currentStep => 'current',
                default => 'upcoming',
            };

            $active = $this->activeColor ?? Theme::primary();
            $activeBg = $active->backgroundClass();
            $activeRing = "ring-{$active->name}-300 dark:ring-{$active->name}-800";

            $circleClasses = match ($state) {
                'done' => "{$activeBg} text-white",
                'current' => "{$activeBg} text-white ring-2 {$activeRing}",
                default => 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400',
            };
            $labelClasses = $state === 'upcoming'
                ? 'text-gray-500 dark:text-gray-400'
                : 'text-gray-900 dark:text-gray-100 font-medium';

            $items .= sprintf(
                '<div class="flex flex-col items-center flex-1">'
                . '<div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold %s">%d</div>'
                . '<span class="text-xs mt-1 text-center %s">%s</span>'
                . '</div>',
                $circleClasses,
                $index + 1,
                $labelClasses,
                htmlspecialchars($label, ENT_QUOTES),
            );

            if ($index < $this->totalSteps - 1) {
                $connectorClasses = $index < $this->currentStep ? $activeBg : 'bg-gray-200 dark:bg-gray-700';
                $items .= sprintf('<div class="flex-1 h-0.5 mt-4 %s"></div>', $connectorClasses);
            }
        }

        return sprintf('<div class="flex items-start">%s</div>', $items);
    }

    private function renderButtons(): string
    {
        if ($this->backAction === null && $this->nextAction === null) {
            return '';
        }

        $buttons = '';
        if ($this->backAction !== null) {
            $buttons .= $this->namedSubmitButton(
                $this->backAction,
                $this->backLabel,
                'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-medium px-4 py-2 rounded-lg',
            );
        }
        if ($this->nextAction !== null) {
            $active = $this->activeColor ?? Theme::primary();
            $hoverShade = min($active->shade + 100, 900);
            $hover = Color::of($active->name, $hoverShade)->backgroundClass();

            $buttons .= $this->namedSubmitButton(
                $this->nextAction,
                $this->nextLabel,
                "{$active->backgroundClass()} hover:{$hover} text-white font-medium px-4 py-2 rounded-lg transition-colors",
            );
        }

        return sprintf('<div class="flex flex-row gap-3 justify-between">%s</div>', $buttons);
    }

    /**
     * A plain hidden `_action` field (as Form::make() uses) is shared by
     * every field in ONE <form> — it can't tell Back and Next apart. Only
     * the CLICKED submit button's own name/value pair is included in the
     * POST, so putting `_action` directly on each button (instead of a
     * shared hidden input) is what lets one <form> dispatch to two
     * different onXxx() handlers depending on which button was pressed.
     */
    private function namedSubmitButton(string $action, string $label, string $classes): string
    {
        return sprintf(
            '<button type="submit" name="_action" value="%s" class="%s">%s</button>',
            htmlspecialchars($action, ENT_QUOTES),
            htmlspecialchars($classes, ENT_QUOTES),
            htmlspecialchars($label, ENT_QUOTES),
        );
    }
}
