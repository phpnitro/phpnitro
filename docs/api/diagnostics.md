# Package `diagnostics`

## `Engine\Diagnostics\CrashReporter` (class)

firebase_crashlytics/Sentry equivalent, self-hosted: no external account needed (none available in this environment). PHP errors are captured via set_exception_handler()/set_error_handler() and JS errors via window.onerror (see assets/js/diagnostics.js), both POSTing to a single app-provided endpoint instead of a third-party SaaS — swap CrashReporter::install()'s $reportUrl for a real Sentry/Crashlytics- compatible endpoint later without changing call sites.

### `static install(string $reportUrl): void`

### `static report(string $type, string $message, string $location, string $trace): void`
