<?php

namespace Engine\App;

use Engine\Button;
use Engine\Column;
use Engine\Device\AppIcon;
use Engine\Device\BackgroundTask;
use Engine\Device\Battery;
use Engine\Device\Bluetooth;
use Engine\Device\Brightness;
use Engine\Device\CalendarEvents;
use Engine\Device\Camera;
use Engine\Device\Contacts;
use Engine\Device\DeviceId;
use Engine\Device\Fingerprint;
use Engine\Device\Geofence;
use Engine\Device\ImagePicker;
use Engine\Device\InAppPurchase;
use Engine\Device\Microphone;
use Engine\Device\Nfc;
use Engine\Device\Notify;
use Engine\Device\Printer;
use Engine\Device\SecureStorage;
use Engine\Device\Sensors;
use Engine\Device\Share;
use Engine\Device\Sound;
use Engine\Device\Torch;
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
            Button::make(
                'Partager',
                onClick: Share::onClick('Regarde cette app faite avec PhpNitro !', 'PhpNitro Demo'),
                classes: self::BUTTON_CLASSES,
            ),
            Row::make([
                Button::make('Icône bleue', onClick: AppIcon::onClick('alt'), classes: self::BUTTON_CLASSES),
                Button::make('Icône par défaut', onClick: AppIcon::onClick('default'), classes: self::BUTTON_CLASSES),
            ], 'flex gap-2'),
            Column::make([
                ImagePicker::hiddenField('photo', 'photo_field'),
                Button::make(
                    'Choisir une image',
                    onClick: ImagePicker::pickOnClick('photo_preview', 'photo_field'),
                    classes: self::BUTTON_CLASSES,
                ),
                ImagePicker::previewElement('photo_preview'),
            ], 'flex flex-col gap-2'),
            Text::make('Capteurs, capacités bas niveau', 'text-lg font-semibold text-gray-900 dark:text-gray-100'),
            Row::make([
                Button::make('Torche', onClick: Torch::onClick(), classes: self::BUTTON_CLASSES),
                Button::make('Luminosité 50%', onClick: Brightness::setOnClick(0.5), classes: self::BUTTON_CLASSES),
            ], 'flex gap-2'),
            Row::make([
                Button::make('Batterie', onClick: Battery::onClick('battery_out'), classes: self::BUTTON_CLASSES),
                Battery::outputElement('battery_out'),
            ], 'flex items-center gap-2'),
            Row::make([
                Button::make('ID device', onClick: DeviceId::onClick('device_id_out'), classes: self::BUTTON_CLASSES),
                DeviceId::outputElement('device_id_out'),
            ], 'flex items-center gap-2'),
            Row::make([
                Button::make('Bluetooth', onClick: Bluetooth::onClick('bt_out'), classes: self::BUTTON_CLASSES),
                Bluetooth::outputElement('bt_out'),
            ], 'flex items-center gap-2'),
            Row::make([
                Button::make(
                    'Accéléromètre',
                    onClick: Sensors::startOnClick(Sensors::ACCELEROMETER, 'sensor_out'),
                    classes: self::BUTTON_CLASSES,
                ),
                Sensors::outputElement('sensor_out'),
            ], 'flex items-center gap-2'),
            Row::make([
                Button::make(
                    'Stocker un secret',
                    onClick: SecureStorage::storeOnClick('demo_key', 'valeur secrète'),
                    classes: self::BUTTON_CLASSES,
                ),
                Button::make(
                    'Lire le secret',
                    onClick: SecureStorage::retrieveOnClick('demo_key', 'secure_out'),
                    classes: self::BUTTON_CLASSES,
                ),
                SecureStorage::outputElement('secure_out'),
            ], 'flex items-center gap-2'),
            Row::make([
                Button::make('Contacts', onClick: Contacts::onClick('contacts_out'), classes: self::BUTTON_CLASSES),
                Contacts::outputElement('contacts_out'),
            ], 'flex items-center gap-2'),
            Row::make([
                Button::make('Calendrier', onClick: CalendarEvents::onClick('calendar_out'), classes: self::BUTTON_CLASSES),
                CalendarEvents::outputElement('calendar_out'),
            ], 'flex items-center gap-2'),
            Row::make([
                Button::make(
                    'Planifier tâche de fond',
                    onClick: BackgroundTask::scheduleOnClick('/api/ping'),
                    classes: self::BUTTON_CLASSES,
                ),
                Button::make('Annuler', onClick: BackgroundTask::cancelOnClick(), classes: self::BUTTON_CLASSES),
            ], 'flex gap-2'),
            Row::make([
                Button::make('Écouter NFC', onClick: Nfc::startOnClick('nfc_out'), classes: self::BUTTON_CLASSES),
                Button::make('Arrêter', onClick: Nfc::stopOnClick(), classes: self::BUTTON_CLASSES),
                Nfc::outputElement('nfc_out'),
            ], 'flex items-center gap-2'),
            Row::make([
                Button::make(
                    'Produits (achat intégré)',
                    onClick: InAppPurchase::queryOnClick(['demo_product'], 'iap_out'),
                    classes: self::BUTTON_CLASSES,
                ),
                Button::make('Acheter demo_product', onClick: InAppPurchase::purchaseOnClick('demo_product'), classes: self::BUTTON_CLASSES),
                InAppPurchase::outputElement('iap_out'),
            ], 'flex items-center gap-2'),
            Row::make([
                Button::make(
                    'Activer zone (Paris, 200m)',
                    onClick: Geofence::addOnClick('paris_demo', 48.8566, 2.3522, 200),
                    classes: self::BUTTON_CLASSES,
                ),
                Button::make('Désactiver', onClick: Geofence::removeOnClick('paris_demo'), classes: self::BUTTON_CLASSES),
            ], 'flex gap-2'),
            Text::make('Carte (OpenStreetMap, zéro clé API)', 'text-lg font-semibold text-gray-900 dark:text-gray-100'),
            MapView::make(48.8566, 2.3522),
            Text::make('Contenu live (StreamBuilder, polling toutes les 2s)', 'text-lg font-semibold text-gray-900 dark:text-gray-100'),
            StreamBuilder::make('/fragment/server-time', Text::make('Chargement...')),
            Link::make("Retour à l'accueil", '/'),
        ], 'flex flex-col gap-4 p-4'));
    }
}
