(function () {
  // Native first: a real AlertDialog (Android, window.AndroidNative) or
  // UIAlertController (iOS, window.iOSNative — see WebAppInterface.swift)
  // instead of the WebView's own alert()/confirm(), which render as a
  // plain browser-style popup, not a native dialog.
  function bridge() {
    return window.AndroidNative || window.iOSNative || null;
  }

  function alert(message, title) {
    const native = bridge();
    if (native && native.showAlertDialog) {
      native.showAlertDialog(title || '', message);
      return;
    }
    window.alert(message);
  }

  function confirm(message, title, onConfirm, onCancel) {
    const native = bridge();
    if (native && native.showConfirmDialog) {
      window.onNativeConfirmResult = function (confirmed) {
        window.onNativeConfirmResult = null;
        if (confirmed) {
          onConfirm && onConfirm();
        } else {
          onCancel && onCancel();
        }
      };
      native.showConfirmDialog(title || '', message);
      return;
    }

    if (window.confirm(message)) {
      onConfirm && onConfirm();
    } else {
      onCancel && onCancel();
    }
  }

  window.phpxDialogs = { alert: alert, confirm: confirm };
})();
