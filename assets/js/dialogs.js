(function () {
  // Native first: a real Android AlertDialog (window.AndroidNative,
  // WebAppInterface.kt) instead of the WebView's own alert()/confirm(),
  // which render as a plain browser-style popup, not a native dialog.
  function alert(message, title) {
    if (window.AndroidNative && window.AndroidNative.showAlertDialog) {
      window.AndroidNative.showAlertDialog(title || '', message);
      return;
    }
    window.alert(message);
  }

  function confirm(message, title, onConfirm, onCancel) {
    if (window.AndroidNative && window.AndroidNative.showConfirmDialog) {
      window.onNativeConfirmResult = function (confirmed) {
        window.onNativeConfirmResult = null;
        if (confirmed) {
          onConfirm && onConfirm();
        } else {
          onCancel && onCancel();
        }
      };
      window.AndroidNative.showConfirmDialog(title || '', message);
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
