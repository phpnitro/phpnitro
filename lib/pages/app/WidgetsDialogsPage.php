<?php

namespace Engine\App;

use Engine\BottomNavigation;
use Engine\Column;
use Engine\Dialogs\AlertButton;
use Engine\Dialogs\ConfirmButton;
use Engine\Divider;
use Engine\Flash;
use Engine\FlashMessage;
use Engine\Link;
use Engine\Screen;
use Engine\SingleScrollView;
use Engine\Text;
use Engine\Widget;

final class WidgetsDialogsPage extends Screen
{
    protected function initialState(): array
    {
        return [];
    }

    protected function onConfirmDemo(array $data): ?string
    {
        Flash::set('Confirmé ! (action reçue par le serveur)', 'success');

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
        $children = [
            Text::make('Boîtes de dialogue', 'text-2xl font-bold text-gray-900 dark:text-gray-100'),
            FlashMessage::make(),
        ];

        $children[] = $this->section(
            'AlertButton — dialogue natif Android, ou window.alert() en repli navigateur',
            AlertButton::make('Ceci est une vraie boîte de dialogue Android (AlertDialog), pas un window.alert().', label: 'Afficher une alerte', title: 'Info'),
        );
        $children[] = $this->section(
            "ConfirmButton — n'appelle le serveur QUE si tu confirmes",
            ConfirmButton::make('Confirmer cette action de démo ?', action: 'confirmDemo', label: 'Demander confirmation'),
        );

        $children[] = Link::make('Retour à la vitrine', '/widgets');
        $children[] = BottomNavigation::make(AppNav::items(), variant: BottomNavigation::VARIANT_PILLS);

        return SingleScrollView::make(Column::make($children, 'flex flex-col gap-4 p-4'));
    }
}
