<?php

namespace Engine\Device\Tests;

use Engine\Device\AlarmScheduler;
use Engine\Device\BackgroundTask;
use Engine\Device\Battery;
use Engine\Device\Bluetooth;
use Engine\Device\Brightness;
use Engine\Device\CalendarEvents;
use Engine\Device\Contacts;
use Engine\Device\DeviceId;
use Engine\Device\Geofence;
use Engine\Device\InAppPurchase;
use Engine\Device\Nfc;
use Engine\Device\SecureStorage;
use Engine\Device\Sensors;
use Engine\Device\Torch;
use PHPUnit\Framework\TestCase;

final class MoreDeviceTest extends TestCase
{
    public function testSensorsStartOnClickPassesTypeAndOutputId(): void
    {
        $js = Sensors::startOnClick(Sensors::ACCELEROMETER, 'sensor_out');

        $this->assertSame("phpxDevice.startSensor(1, 'sensor_out')", $js);
    }

    public function testSensorsStopOnClickPassesType(): void
    {
        $this->assertSame('phpxDevice.stopSensor(4)', Sensors::stopOnClick(Sensors::GYROSCOPE));
    }

    public function testSensorsConstantsMatchAndroidSensorTypeInts(): void
    {
        $this->assertSame(1, Sensors::ACCELEROMETER);
        $this->assertSame(2, Sensors::MAGNETIC_FIELD);
        $this->assertSame(4, Sensors::GYROSCOPE);
    }

    public function testTorchOnClick(): void
    {
        $this->assertSame('phpxDevice.toggleTorch()', Torch::onClick());
    }

    public function testBrightnessSetOnClickPassesLevel(): void
    {
        $this->assertSame('phpxDevice.setScreenBrightness(0.5)', Brightness::setOnClick(0.5));
    }

    public function testBatteryOnClickPassesOutputId(): void
    {
        $this->assertSame("phpxDevice.showBatteryLevel('battery_out')", Battery::onClick('battery_out'));
    }

    public function testBatteryOutputElementRendersSpanWithId(): void
    {
        $html = Battery::outputElement('battery_out')->render();

        $this->assertStringContainsString('id="battery_out"', $html);
    }

    public function testDeviceIdOnClickPassesOutputId(): void
    {
        $this->assertSame("phpxDevice.showDeviceId('device_id_out')", DeviceId::onClick('device_id_out'));
    }

    public function testBluetoothOnClickPassesOutputId(): void
    {
        $this->assertSame("phpxDevice.showBluetoothInfo('bt_out')", Bluetooth::onClick('bt_out'));
    }

    public function testSecureStorageStoreOnClickPassesKeyAndValue(): void
    {
        $js = SecureStorage::storeOnClick('token', 'abc123');

        $this->assertStringContainsString('"token"', $js);
        $this->assertStringContainsString('"abc123"', $js);
    }

    public function testSecureStorageRetrieveOnClickPassesKeyAndOutputId(): void
    {
        $js = SecureStorage::retrieveOnClick('token', 'secure_out');

        $this->assertStringContainsString('"token"', $js);
        $this->assertStringContainsString("'secure_out'", $js);
    }

    public function testSecureStorageRemoveOnClickPassesKey(): void
    {
        $this->assertStringContainsString('"token"', SecureStorage::removeOnClick('token'));
    }

    public function testContactsOnClickPassesOutputId(): void
    {
        $this->assertSame("phpxDevice.listContacts('contacts_out')", Contacts::onClick('contacts_out'));
    }

    public function testCalendarEventsOnClickPassesOutputId(): void
    {
        $this->assertSame("phpxDevice.listUpcomingEvents('calendar_out')", CalendarEvents::onClick('calendar_out'));
    }

    public function testBackgroundTaskScheduleOnClickPassesEndpointAndInterval(): void
    {
        $js = BackgroundTask::scheduleOnClick('/api/ping', 30);

        $this->assertStringContainsString('"\/api\/ping"', $js);
        $this->assertStringContainsString('30', $js);
    }

    public function testBackgroundTaskScheduleOnClickDefaultsToFifteenMinutes(): void
    {
        $this->assertStringContainsString('15', BackgroundTask::scheduleOnClick('/api/ping'));
    }

    public function testBackgroundTaskCancelOnClick(): void
    {
        $this->assertSame('phpxDevice.cancelBackgroundTask()', BackgroundTask::cancelOnClick());
    }

    public function testAlarmSchedulerOnClickPassesAllParamsAsJson(): void
    {
        $js = AlarmScheduler::onClick(1, 3600, 'Rappel', 'N\'oublie pas');

        $this->assertStringContainsString('phpxDevice.scheduleAlarm(1, 3600,', $js);
        $this->assertStringContainsString('"Rappel"', $js);
        $this->assertStringContainsString("N'oublie pas", $js);
    }

    public function testNfcStartOnClickPassesOutputId(): void
    {
        $this->assertSame("phpxDevice.startNfc('nfc_out')", Nfc::startOnClick('nfc_out'));
    }

    public function testNfcStopOnClick(): void
    {
        $this->assertSame('phpxDevice.stopNfc()', Nfc::stopOnClick());
    }

    public function testInAppPurchaseQueryOnClickEncodesProductIdsAsJson(): void
    {
        $js = InAppPurchase::queryOnClick(['product_a', 'product_b'], 'iap_out');

        $this->assertStringContainsString('["product_a","product_b"]', $js);
        $this->assertStringContainsString("'iap_out'", $js);
    }

    public function testInAppPurchasePurchaseOnClickPassesProductId(): void
    {
        $this->assertSame("phpxDevice.purchaseProduct('product_a')", InAppPurchase::purchaseOnClick('product_a'));
    }

    public function testGeofenceAddOnClickPassesAllParams(): void
    {
        $js = Geofence::addOnClick('zone_1', 48.8566, 2.3522, 200);

        $this->assertSame("phpxDevice.addGeofence('zone_1', 48.8566, 2.3522, 200)", $js);
    }

    public function testGeofenceRemoveOnClickPassesId(): void
    {
        $this->assertSame("phpxDevice.removeGeofence('zone_1')", Geofence::removeOnClick('zone_1'));
    }
}
