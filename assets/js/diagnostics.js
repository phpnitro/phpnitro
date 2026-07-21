// Client-side half of Engine\Diagnostics\CrashReporter — captures JS
// errors/unhandled rejections and POSTs them to the same report endpoint
// the PHP side uses, so a single dashboard/log sees both. Opt-in: only
// activates if the page defines window.phpxCrashReportUrl (set by
// CrashReporter::install()'s report URL, threaded into a page via a small
// inline script — see the Diagnostics widget demo).
(function () {
  function send(payload) {
    const url = window.phpxCrashReportUrl;
    if (!url) return;
    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      keepalive: true,
    }).catch(() => {});
  }

  window.addEventListener('error', (event) => {
    send({
      type: 'JS Error',
      message: event.message,
      location: `${event.filename}:${event.lineno}:${event.colno}`,
      trace: event.error && event.error.stack ? event.error.stack : '',
      at: new Date().toISOString(),
    });
  });

  window.addEventListener('unhandledrejection', (event) => {
    send({
      type: 'Unhandled Promise Rejection',
      message: String(event.reason),
      location: '',
      trace: event.reason && event.reason.stack ? event.reason.stack : '',
      at: new Date().toISOString(),
    });
  });
})();
