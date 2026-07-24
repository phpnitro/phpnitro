# Package `launcher`

## `Engine\Launcher\Launcher` (class)

url_launcher equivalent — opens an external URI (website, phone dialer, email client, SMS app) via the system's own handler, not this app's WebView (see WebAppInterface.launchUrl() / MainActivity.kt's shouldOverrideUrlLoading()). A JS trigger, not a widget — attach it to any button via Button::make($label, onClick: Launcher::call('+229...')).

### `static openUrl(string $url): string`

### `static call(string $phoneNumber): string`

### `static sms(string $phoneNumber, string $body = ''): string`

### `static email(string $address, string $subject = '', string $body = ''): string`
