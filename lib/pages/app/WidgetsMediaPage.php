<?php

namespace Engine\App;

use Engine\AudioPlayer;
use Engine\BottomNavigation;
use Engine\Column;
use Engine\Divider;
use Engine\FutureBuilder;
use Engine\GoogleTranslate;
use Engine\Link;
use Engine\LinkWrap;
use Engine\Screen;
use Engine\SingleScrollView;
use Engine\Text;
use Engine\VideoPlayer;
use Engine\Widget;

final class WidgetsMediaPage extends Screen
{
    protected function initialState(): array
    {
        return [];
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
            Text::make('Média', 'text-2xl font-bold text-gray-900 dark:text-gray-100'),

            $this->section('AudioPlayer', AudioPlayer::make('/assets/audio/beep.wav')),
            $this->section(
                'VideoPlayer (échantillon public MDN)',
                VideoPlayer::make('https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4'),
            ),
            $this->section('GoogleTranslate', GoogleTranslate::make()),
            $this->section(
                'FutureBuilder — un seul chargement, pas de re-polling',
                FutureBuilder::make('/fragment/server-time', Text::make('Chargement...')),
            ),
            $this->section(
                'LinkWrap — enveloppe n\'importe quel widget dans un lien',
                LinkWrap::make(Text::make('Toute cette zone est cliquable →', 'text-blue-600'), '/widgets'),
            ),

            Link::make('Retour à la vitrine', '/widgets'),
            BottomNavigation::make(AppNav::items(), variant: BottomNavigation::VARIANT_PILLS),
        ], 'flex flex-col gap-4 p-4'));
    }
}
