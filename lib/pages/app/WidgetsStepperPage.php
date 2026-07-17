<?php

namespace Engine\App;

use Engine\BottomNavigation;
use Engine\Column;
use Engine\Link;
use Engine\Screen;
use Engine\SelectBox;
use Engine\SingleScrollView;
use Engine\Stepper;
use Engine\Text;
use Engine\TextField;
use Engine\Widget;

/**
 * A real 3-step wizard (not just a static render): Stepper wraps each
 * step's raw fields in its own <form> and the Back/Next buttons carry the
 * step's data along via Screen::handle() — same $state-across-POSTs
 * mechanism CheckoutPage already uses for validation.
 */
final class WidgetsStepperPage extends Screen
{
    private const LAST_STEP = 2;

    protected function initialState(): array
    {
        return ['step' => 0, 'data' => []];
    }

    /**
     * @param array<string, string> $data
     */
    protected function onStepperBack(array $data): ?string
    {
        $this->state['data'] = [...$this->state['data'], ...$data];
        $this->state['step'] = max($this->state['step'] - 1, 0);

        return null;
    }

    /**
     * @param array<string, string> $data
     */
    protected function onStepperNext(array $data): ?string
    {
        $this->state['data'] = [...$this->state['data'], ...$data];
        $this->state['step'] = min($this->state['step'] + 1, self::LAST_STEP);

        return null;
    }

    /**
     * @param array<string, string> $data
     */
    protected function onStepperReset(array $data): ?string
    {
        $this->state = $this->initialState();

        return null;
    }

    public function build(): Widget
    {
        $step = $this->state['step'];
        $data = $this->state['data'];

        $body = match ($step) {
            0 => Column::make([
                TextField::make('name', label: 'Nom complet', value: $data['name'] ?? ''),
                TextField::make('email', label: 'Email', value: $data['email'] ?? ''),
            ], 'flex flex-col gap-3'),
            1 => Column::make([
                SelectBox::make(
                    'plan',
                    ['free' => 'Gratuit', 'pro' => 'Pro', 'enterprise' => 'Entreprise'],
                    selected: $data['plan'] ?? 'free',
                    label: 'Formule',
                ),
            ], 'flex flex-col gap-3'),
            default => Column::make([
                Text::make('Récapitulatif', 'text-lg font-semibold text-gray-900 dark:text-gray-100'),
                Text::make('Nom : ' . ($data['name'] ?? '—'), 'text-gray-700 dark:text-gray-300'),
                Text::make('Email : ' . ($data['email'] ?? '—'), 'text-gray-700 dark:text-gray-300'),
                Text::make('Formule : ' . ($data['plan'] ?? '—'), 'text-gray-700 dark:text-gray-300'),
            ], 'flex flex-col gap-2'),
        };

        $stepper = Stepper::make(
            currentStep: $step,
            totalSteps: self::LAST_STEP + 1,
            stepLabels: ['Compte', 'Préférences', 'Résumé'],
            body: $body,
            backAction: $step > 0 ? 'stepperBack' : null,
            nextAction: $step < self::LAST_STEP ? 'stepperNext' : 'stepperReset',
            nextLabel: $step < self::LAST_STEP ? 'Suivant' : 'Recommencer',
        );

        return SingleScrollView::make(Column::make([
            Text::make('Stepper', 'text-2xl font-bold text-gray-900 dark:text-gray-100'),
            $stepper,
            Link::make('Retour à la vitrine', '/widgets'),
            BottomNavigation::make(AppNav::items(), variant: BottomNavigation::VARIANT_PILLS),
        ], 'flex flex-col gap-4 p-4'));
    }
}
