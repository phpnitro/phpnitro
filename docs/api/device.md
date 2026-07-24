# Package `device`

## `Engine\Device\AlarmScheduler` (class)

android_alarm_manager_plus equivalent — schedules a notification to fire after a delay, even if the app has since been killed (Android AlarmManager + AlarmReceiver, see WebAppInterface.kt's scheduleAlarm()). A JS trigger, not a widget — attach it to any button via Button::make($label, onClick: AlarmScheduler::onClick(1, 3600, ...)).

### `static onClick(int $requestCode, int $delaySeconds, string $title, string $message): string`

## `Engine\Device\AppIcon` (class)

Switches the home-screen launcher icon at runtime (Android activity-alias toggle — see AndroidManifest.xml's ".MainActivityDefault" ".MainActivityAlt" and WebAppInterface.kt's setAppIcon()). A JS trigger, not a widget — attach it to any button via Button::make($label, onClick: AppIcon::onClick('alt')).

### `static onClick(string $iconKey): string`

## `Engine\Device\BackgroundTask` (class)

WorkManager periodic background execution (android_alarm_manager_plus's "run this repeatedly, even in the background" use case, as distinct from AlarmScheduler's "fire once at a specific time"). Pings $endpoint every $intervalMinutes (WorkManager's own 15-minute floor, not a choice made here) even when the app isn't foregrounded — the worker itself is a dumb HTTP POST (BackgroundPingWorker.kt), it does NOT start the embedded PHP server or run any PHP; point $endpoint at your own hosted backend, not this device's own loopback server.

### `static scheduleOnClick(string $endpoint, int $intervalMinutes = 15): string`

### `static cancelOnClick(): string`

## `Engine\Device\Battery` (class)

### `static onClick(string $outputId): string`

### `static outputElement(string $id, string $classes = 'text-sm text-gray-500 dark:text-gray-400'): Engine\Widget`

## `Engine\Device\Bluetooth` (class)

Adapter state + already-bonded (paired) devices only. A full BLE discovery scan needs a foreground service and more careful location-permission handling than this bridge covers yet — see ROADMAP-PARITE-FLUTTER-REACT-NATIVE.md.

### `static onClick(string $outputId): string`

### `static outputElement(string $id, string $classes = 'text-sm text-gray-500 dark:text-gray-400'): Engine\Widget`

## `Engine\Device\Brightness` (class)

Sets THIS app window's screen brightness (0.0-1.0) — not the system-wide setting, which would need WRITE_SETTINGS (a special-access permission granted through a system settings screen, not a normal runtime prompt).

### `static setOnClick(float $level): string`

## `Engine\Device\CalendarEvents` (class)

Read-only, next 30 days — needs READ_CALENDAR granted. Named CalendarEvents, not Calendar: PHP's own Calendar-ish built-ins/DateTime live in the global namespace, avoid any confusion.

### `static onClick(string $outputId): string`

### `static outputElement(string $id, string $classes = 'text-sm text-gray-500 dark:text-gray-400'): Engine\Widget`

## `Engine\Device\Camera` (class)

Two triggers (open the live preview, capture a native photo) plus two output elements (<video> for the preview, <img> for the captured photo) — composed separately so the developer places each wherever they want and attaches the triggers to their own buttons.

### `static openOnClick(string $videoId): string`

### `static captureOnClick(string $imageId): string`

### `static videoElement(string $id, string $classes = 'w-full max-w-xs rounded-lg bg-black'): Engine\Widget`

### `static imageElement(string $id, string $classes = 'w-full max-w-xs rounded-lg'): Engine\Widget`

## `Engine\Device\Contacts` (class)

Read-only, first 200 contacts by name — needs READ_CONTACTS granted.

### `static onClick(string $outputId): string`

### `static outputElement(string $id, string $classes = 'text-sm text-gray-500 dark:text-gray-400'): Engine\Widget`

## `Engine\Device\DeviceId` (class)

Settings.Secure.ANDROID_ID — resettable on factory reset, different per app signing key since Android 8, not the IMEI/hardware serial (which would need a special permission Play Store apps can't request at all).

### `static onClick(string $outputId): string`

### `static outputElement(string $id, string $classes = 'text-sm text-gray-500 dark:text-gray-400'): Engine\Widget`

## `Engine\Device\Fingerprint` (class)

Triggers a native BiometricPrompt (fingerprint/face unlock) and writes the result text into the element $outputId names. A JS trigger, not a widget — attach onClick() to any button and place outputElement() wherever the result should appear.

### `static onClick(string $outputId): string`

### `static outputElement(string $id, string $classes = 'text-sm text-gray-500 dark:text-gray-400'): Engine\Widget`

## `Engine\Device\Geofence` (class)

Real geofencing (zone + enter/exit), via Play Services' GeofencingClient — not BackgroundTask's periodic ping, and not a manual distance check. Requires ACCESS_FINE_LOCATION (already requested at startup) and ACCESS_BACKGROUND_LOCATION (declared in the manifest) for transitions to fire while the app isn't in the foreground.

### `static addOnClick(string $id, float $latitude, float $longitude, float $radiusMeters): string`

### `static removeOnClick(string $id): string`

## `Engine\Device\ImagePicker` (class)

Native image picker (system gallery/file app) with a live preview. The picked image ends up as a data: URL in the hidden field the developer places via hiddenField() — submits as part of a normal Form POST, same as before, but the trigger/preview/hidden field are now composed separately instead of bundled into one opinionated widget.

### `static pickOnClick(string $previewId, string $hiddenFieldId): string`

### `static hiddenField(string $name, string $id): Engine\Widget`

### `static previewElement(string $id, string $classes = 'w-full max-w-xs rounded-lg'): Engine\Widget`

## `Engine\Device\InAppPurchase` (class)

Google Play Billing (one-time products only — no subscriptions, no consumables acknowledgment flow yet). Product IDs must already exist in Play Console under the app's own package; there is no sandbox usable outside a real Play Console account, so this has never run against a real product (see ROADMAP-PARITE-FLUTTER-REACT-NATIVE.md).

### `static queryOnClick(array $productIds, string $outputId): string`

### `static purchaseOnClick(string $productId): string`

### `static outputElement(string $id, string $classes = 'text-sm text-gray-500 dark:text-gray-400'): Engine\Widget`

## `Engine\Device\Microphone` (class)

Records $durationMs of real audio via native MediaRecorder (see WebAppInterface.kt's recordAudioClip — confirmed on real hardware that the WebView-mediated getUserMedia({audio:true}) unreliably fails with "Could not start audio source" on some devices, even with the permission already granted) and plays it back, writing status text into the element $outputId names. Falls back to plain getUserMedia only if no native bridge is present (browser testing). The developer places outputElement() wherever they want and attaches onClick() to their own button, instead of a widget rendering both together.

### `static onClick(string $outputId, int $durationMs = 3000): string`

### `static outputElement(string $id, string $classes = 'text-sm text-gray-500 dark:text-gray-400'): Engine\Widget`

## `Engine\Device\Nfc` (class)

Read-only NDEF tag scanning. NFC is push-based, not poll-based like Bluetooth: there's nothing to "call" to get a tag, so this mirrors Sensors' start/stop-listening shape instead — the native side dispatches a foreground scan while listening is on, and reports whatever tag shows up whenever it shows up. No write support (writing NDEF records to a tag is a separate, riskier capability, left out here).

### `static startOnClick(string $outputId): string`

### `static stopOnClick(): string`

### `static outputElement(string $id, string $classes = 'text-sm text-gray-500 dark:text-gray-400'): Engine\Widget`

## `Engine\Device\Notify` (class)

Triggers a real system notification via native NotificationCompat (see WebAppInterface.showNotification) — works fully offline, no Firebase or network call needed. A JS trigger, not a widget — attach it to any button via Button::make($label, onClick: Notify::onClick(...)).

### `static onClick(string $title, string $message): string`

## `Engine\Device\Printer` (class)

Turns the current page into a PDF via Android's native print pipeline (WebView.createPrintDocumentAdapter + PrintManager — the system "Save as PDF" flow) — no PHP PDF library needed. A JS trigger, not a widget — attach it to any button via Button::make($label, onClick: Printer::onClick()). Named Printer, not Print — `print` is a reserved word in PHP and can't be used as a class name.

### `static onClick(): string`

## `Engine\Device\SecureStorage` (class)

Keychain/Keystore equivalent — an Android Keystore-backed EncryptedSharedPreferences store (AES256-GCM), for tokens that shouldn't sit in Engine\Preferences\'s plain SQLite table. Client-side only, same as sensor readings/geolocation: PHP emits the trigger, the value lives (encrypted) on the device, never round-tripped back into a PHP request automatically — read it into an output element, or post it to your own action if a server-side check needs it.

### `static storeOnClick(string $key, string $value): string`

### `static retrieveOnClick(string $key, string $outputId): string`

### `static removeOnClick(string $key): string`

### `static outputElement(string $id, string $classes = 'text-sm text-gray-500 dark:text-gray-400'): Engine\Widget`

## `Engine\Device\Sensors` (class)

Accelerometer/gyroscope/compass — no Web API equivalent reliable enough across browsers to fall back to (DeviceMotionEvent only approximates the accelerometer, inconsistently), so this is native-only: no bridge means no readings, not a degraded simulation.

### `static startOnClick(int $sensorType, string $outputId): string`

### `static stopOnClick(int $sensorType): string`

### `static outputElement(string $id, string $classes = 'text-sm text-gray-500 dark:text-gray-400'): Engine\Widget`

## `Engine\Device\Share` (class)

Triggers the real native share sheet (Android's Intent.ACTION_SEND chooser, iOS's UIActivityViewController — see WebAppInterface.share()) — falls back to the Web Share API outside a native shell. A JS trigger, not a widget — attach it to any button via Button::make($label, onClick: Share::onClick(...)).

### `static onClick(string $text, string $title = ''): string`

## `Engine\Device\Sound` (class)

Plays a sound file through the device speaker via native MediaPlayer (see WebAppInterface.playSound) — keeps playing correctly across screen lock / audio focus changes, unlike a WebView <audio> tag. A JS trigger, not a widget — attach it to any button via Button::make($label, onClick: Sound::onClick($url)).

### `static onClick(string $url): string`

## `Engine\Device\Torch` (class)

Flashlight toggle, independent of Camera's photo/video capture (which needs a live camera session; this only needs CameraManager.setTorchMode). No web fallback — browsers have no torch API outside a getUserMedia video track's (spottily supported) ImageCapture extension.

### `static onClick(): string`

## `Engine\Device\Vibrate` (class)

A JS trigger, not a widget — attach it to any button via Button::make($label, onClick: Vibrate::onClick(200)) instead of being stuck with a pre-styled widget's own rendering.

### `static onClick(int $milliseconds = 200): string`
