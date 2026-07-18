<?php

namespace Engine\App;

use Engine\Button;
use Engine\CircularProgress;
use Engine\Column;
use Engine\DatePicker;
use Engine\Divider;
use Engine\Form;
use Engine\Icon;
use Engine\IconButton;
use Engine\Link;
use Engine\ProgressBar;
use Engine\Screen;
use Engine\SelectBox;
use Engine\SingleScrollView;
use Engine\SwitchToggle;
use Engine\Text;
use Engine\Textarea;
use Engine\TimePicker;
use Engine\Widget;

final class WidgetsFormsPage extends Screen
{
    protected function initialState(): array
    {
        return [];
    }

    /**
     * Demo submit target for the widgets below that need a real <form> to
     * render inside — stays on this page either way.
     *
     * @param array<string, string> $data
     */
    protected function onNoop(array $data): ?string
    {
        return null;
    }

    private function section(string $caption, Widget $example): Widget
    {
        return Column::make([
            Text::make($caption, 'text-sm text-gray-500 dark:text-gray-400'),
            $example,
            Divider::make(),
        ], 'flex flex-col gap-2');
    }

    public function build(): Widget
    {
        return SingleScrollView::make(Column::make([
            Text::make('Formulaires', 'text-2xl font-bold text-gray-900 dark:text-gray-100'),

            Form::make([
                DatePicker::make('date', label: 'DatePicker'),
                TimePicker::make('time', label: 'TimePicker'),
                Textarea::make('note', label: 'Textarea', placeholder: 'Un commentaire...'),
                SelectBox::make('mode', ['standard' => 'Standard', 'express' => 'Express'], label: 'SelectBox'),
                SwitchToggle::make('notify', 'SwitchToggle'),
                Button::make('Valider (ne fait rien, juste une démo)', action: 'noop'),
            ], action: 'noop'),

            Divider::make(),

            $this->section('IconButton', IconButton::make(Icon::bolt(), ariaLabel: 'Action rapide')),
            $this->section('ProgressBar — 65%', ProgressBar::make(0.65)),
            $this->section('CircularProgress — 40%', CircularProgress::make(0.4)),

            Link::make('Retour à la vitrine', '/widgets'),
        ], 'flex flex-col gap-4 p-4'));
    }
}
