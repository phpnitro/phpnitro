// connectivity_plus equivalent — network status, online/offline changes,
// and (native shell only) the actual connection type (wifi/cellular/none),
// via ConnectivityManager rather than the browser's limited/inconsistent
// navigator.connection API. window.phpxConnectivity is usable standalone
// by any script (e.g. before an upload, or an Engine\Countries lookup that
// needs live network vs. relying purely on the offline dataset); it's also
// what ConnectivityBadge's own inline script (see
// Engine\Connectivity\ConnectivityBadge) reads to paint itself.
(function () {
  function nativeBridge() {
    return window.AndroidNative || window.iOSNative || null;
  }

  function isOnline() {
    return navigator.onLine;
  }

  // Best-effort: only the Android bridge implements this today (iOS's
  // WebAppInterface.swift doesn't have a Reachability-based equivalent
  // yet — untested/no Mac available, see ios/README.md). Falls back to
  // "unknown" rather than guessing.
  function connectionType() {
    const bridge = nativeBridge();
    if (bridge && bridge.getConnectionType) {
      return bridge.getConnectionType();
    }
    return isOnline() ? 'unknown' : 'none';
  }

  function onChange(callback) {
    window.addEventListener('online', () => callback(true, connectionType()));
    window.addEventListener('offline', () => callback(false, connectionType()));
  }

  window.phpxConnectivity = { isOnline, connectionType, onChange };

  function paintBadges() {
    document.querySelectorAll('[data-connectivity-badge]').forEach((el) => {
      const online = isOnline();
      el.textContent = online ? el.dataset.onlineLabel : el.dataset.offlineLabel;
      el.className = online ? el.dataset.onlineClass : el.dataset.offlineClass;
    });
  }

  document.addEventListener('DOMContentLoaded', paintBadges);
  document.addEventListener('phpx:navigated', paintBadges);
  onChange(paintBadges);
})();
