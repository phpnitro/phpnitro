<?php

namespace Engine\App;

use Engine\Button;
use Engine\Column;
use Engine\Device\Camera;
use Engine\Device\Fingerprint;
use Engine\Device\ImagePicker;
use Engine\Device\Microphone;
use Engine\Device\Notify;
use Engine\Device\Printer;
use Engine\Device\Sound;
use Engine\Device\Vibrate;
use Engine\Link;
use Engine\LocationButton;
use Engine\Maps\MapView;
use Engine\Row;
use Engine\Screen;
use Engine\SingleScrollView;
use Engine\StreamBuilder;
use Engine\Text;
use Engine\Widget;

final class DevicePage extends Screen
{
    private const BUTTON_CLASSES = 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 '
        . 'font-medium px-4 py-2 rounded-lg';

    protected function initialState(): array
    {
        return [];
    }

    public function build(): Widget
    {
        return SingleScrollView::make(Column::make([
            Text::make('Capacités du device', 'text-2xl font-bold text-gray-900 dark:text-gray-100'),
            Button::make('Vibrer', onClick: Vibrate::onClick(), classes: self::BUTTON_CLASSES),
            LocationButton::make(),
            Row::make([
                Button::make('Activer le micro', onClick: Microphone::onClick('mic_out'), classes: self::BUTTON_CLASSES),
                Microphone::outputElement('mic_out'),
            ], 'flex items-center gap-2'),
            Column::make([
                Camera::videoElement('cam_video'),
                Camera::imageElement('cam_photo'),
                Row::make([
                    Button::make('Activer la caméra', onClick: Camera::openOnClick('cam_video'), classes: self::BUTTON_CLASSES),
                    Button::make('Photo native', onClick: Camera::captureOnClick('cam_photo'), classes: self::BUTTON_CLASSES),
                ], 'flex gap-2'),
            ], 'flex flex-col gap-2'),
            Row::make([
                Button::make('Authentifier', onClick: Fingerprint::onClick('fp_out'), classes: self::BUTTON_CLASSES),
                Fingerprint::outputElement('fp_out'),
            ], 'flex items-center gap-2'),
            Button::make(
                'Notifier',
                onClick: Notify::onClick('PhpNitro', 'Ceci est une notification native.'),
                classes: self::BUTTON_CLASSES,
            ),
            Button::make('Jouer un son', onClick: Sound::onClick('/assets/audio/beep.wav'), classes: self::BUTTON_CLASSES),
            Button::make('Imprimer', onClick: Printer::onClick(), classes: self::BUTTON_CLASSES),
            Column::make([
                ImagePicker::hiddenField('photo', 'photo_field'),
                Button::make(
                    'Choisir une image',
                    onClick: ImagePicker::pickOnClick('photo_preview', 'photo_field'),
                    classes: self::BUTTON_CLASSES,
                ),
                ImagePicker::previewElement('photo_preview'),
            ], 'flex flex-col gap-2'),
            Text::make('Carte (OpenStreetMap, zéro clé API)', 'text-lg font-semibold text-gray-900 dark:text-gray-100'),
            MapView::make(48.8566, 2.3522),
            Text::make('Contenu live (StreamBuilder, polling toutes les 2s)', 'text-lg font-semibold text-gray-900 dark:text-gray-100'),
            StreamBuilder::make('/fragment/server-time', Text::make('Chargement...')),
            Link::make("Retour à l'accueil", '/'),
        ], 'flex flex-col gap-4 p-4'));
    }
}
