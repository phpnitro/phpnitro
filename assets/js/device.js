// Both native shells expose the same method names on window.AndroidNative
// (Android, WebAppInterface.kt, synchronous @JavascriptInterface calls) or
// window.iOSNative (iOS, WebAppInterface.swift — a JS shim backed by an
// async WKScriptMessageHandler, but with an identical call shape from this
// side) — everything below picks whichever bridge is present rather than
// hardcoding one platform, so this file never needs a per-platform branch.
function phpxNativeBridge() {
  return window.AndroidNative || window.iOSNative || null;
}

window.phpxDevice = {
  vibrate(ms) {
    // Prefer the genuinely-native path (Vibrator/UIImpactFeedbackGenerator
    // via the native bridge) when running inside a native shell; fall back
    // to the standard Web API (still real hardware, but WebView-mediated)
    // everywhere else.
    const bridge = phpxNativeBridge();
    if (bridge && bridge.vibrate) {
      bridge.vibrate(ms);
      return;
    }
    if (navigator.vibrate) {
      navigator.vibrate(ms);
    }
  },

  takeNativePhoto(imgElementId) {
    const bridge = phpxNativeBridge();
    if (!bridge || !bridge.takeNativePhoto) {
      const el = document.getElementById(imgElementId);
      if (el) el.insertAdjacentText('afterend', 'Photo native disponible uniquement dans la coquille Android/iOS.');
      return;
    }

    window.onNativePhotoTaken = (dataUrl) => {
      const el = document.getElementById(imgElementId);
      if (el && dataUrl) {
        el.src = dataUrl;
      }
    };

    bridge.takeNativePhoto();
  },

  locate(outputElementId) {
    const el = document.getElementById(outputElementId);
    if (!navigator.geolocation) {
      if (el) el.textContent = 'Géolocalisation non disponible';
      return;
    }

    navigator.geolocation.getCurrentPosition(
      (pos) => {
        if (el) {
          el.textContent = pos.coords.latitude.toFixed(5) + ', ' + pos.coords.longitude.toFixed(5);
        }
      },
      (err) => {
        if (el) el.textContent = 'Erreur : ' + err.message;
      },
    );
  },

  async openCamera(videoElementId) {
    const el = document.getElementById(videoElementId);
    if (!el || !navigator.mediaDevices) {
      return;
    }

    try {
      const stream = await navigator.mediaDevices.getUserMedia({ video: true });
      el.srcObject = stream;
      await el.play();
    } catch (err) {
      el.insertAdjacentText('afterend', 'Erreur caméra : ' + err.message);
    }
  },

  async openMicrophone(outputElementId) {
    const el = document.getElementById(outputElementId);
    if (!el || !navigator.mediaDevices) {
      return;
    }

    try {
      await navigator.mediaDevices.getUserMedia({ audio: true });
      el.textContent = 'Micro activé';
    } catch (err) {
      el.textContent = 'Erreur micro : ' + err.message;
    }
  },

  // Fingerprint/face unlock. Prefers the native bridge (Android
  // BiometricPrompt or iOS LocalAuthentication/Face ID-Touch ID, via
  // WebAppInterface.showBiometricPrompt) since WebView does NOT implement
  // WebAuthn/FIDO2 platform authenticators the way the Chrome app does —
  // navigator.credentials is unreliable-to-absent inside a WebView even when
  // the device has a fingerprint enrolled. Falls back to WebAuthn only when
  // running in a real browser (e.g. testing the dev server directly).
  fingerprint(outputElementId) {
    const el = document.getElementById(outputElementId);
    const bridge = phpxNativeBridge();

    if (bridge && bridge.showBiometricPrompt) {
      window.onNativeBiometricResult = (success, message) => {
        if (el) el.textContent = success ? 'Authentification réussie ✓' : ('Échec : ' + message);
      };
      bridge.showBiometricPrompt();
      return;
    }

    this.webAuthnFingerprint(outputElementId);
  },

  async webAuthnFingerprint(outputElementId) {
    const el = document.getElementById(outputElementId);
    if (!window.PublicKeyCredential) {
      if (el) el.textContent = 'Authentification biométrique non disponible sur ce navigateur.';
      return;
    }

    try {
      const available = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
      if (!available) {
        if (el) el.textContent = 'Aucun capteur biométrique disponible.';
        return;
      }

      let credentialId = sessionStorage.getItem('phpxFingerprintCredentialId');

      if (!credentialId) {
        const credential = await navigator.credentials.create({
          publicKey: {
            challenge: crypto.getRandomValues(new Uint8Array(32)),
            rp: { name: 'PHP Mobile App' },
            user: {
              id: crypto.getRandomValues(new Uint8Array(16)),
              name: 'app-user',
              displayName: 'App user',
            },
            pubKeyCredParams: [{ type: 'public-key', alg: -7 }],
            authenticatorSelection: { authenticatorAttachment: 'platform', userVerification: 'required' },
            timeout: 30000,
          },
        });
        credentialId = btoa(String.fromCharCode(...new Uint8Array(credential.rawId)));
        sessionStorage.setItem('phpxFingerprintCredentialId', credentialId);
      }

      await navigator.credentials.get({
        publicKey: {
          challenge: crypto.getRandomValues(new Uint8Array(32)),
          allowCredentials: [{
            type: 'public-key',
            id: Uint8Array.from(atob(credentialId), (c) => c.charCodeAt(0)),
          }],
          userVerification: 'required',
          timeout: 30000,
        },
      });

      if (el) el.textContent = 'Authentification réussie ✓';
    } catch (err) {
      if (el) el.textContent = 'Échec : ' + err.message;
    }
  },

  // Native MediaPlayer/AVAudioPlayer (keeps playing across screen lock /
  // audio focus changes) with a plain <audio> fallback for browser testing.
  playSound(url) {
    const bridge = phpxNativeBridge();
    if (bridge && bridge.playSound) {
      bridge.playSound(url);
      return;
    }
    new Audio(url).play().catch(() => {});
  },

  // Real system notification (native NotificationCompat/UNUserNotificationCenter),
  // independent of any push service — works fully offline. Falls back to the
  // Web Notifications API in a browser.
  async notify(title, message) {
    const bridge = phpxNativeBridge();
    if (bridge && bridge.showNotification) {
      bridge.showNotification(title, message);
      return;
    }

    if (!('Notification' in window)) {
      return;
    }

    if (Notification.permission === 'default') {
      await Notification.requestPermission();
    }

    if (Notification.permission === 'granted') {
      new Notification(title, { body: message });
    }
  },

  // Native share sheet (Intent.ACTION_SEND chooser on Android,
  // UIActivityViewController on iOS) — a plain WebView page has no way to
  // trigger this on its own, unlike a real browser tab's Web Share API
  // (navigator.share), which is the fallback here for browser testing.
  share(text, title) {
    const bridge = phpxNativeBridge();
    if (bridge && bridge.share) {
      bridge.share(text, title || '');
      return;
    }
    if (navigator.share) {
      navigator.share({ text, title: title || undefined }).catch(() => {});
    }
  },

  // Switches the home-screen launcher icon (Android activity-alias
  // toggle, see WebAppInterface.kt's setAppIcon()) — no web/browser
  // equivalent exists (a page can't change what icon its own PWA/tab uses
  // from JS), so this is a silent no-op outside a native shell.
  setAppIcon(iconKey) {
    const bridge = phpxNativeBridge();
    if (bridge && bridge.setAppIcon) {
      bridge.setAppIcon(iconKey);
    }
  },

  // Native print pipeline (WebView.createPrintDocumentAdapter + PrintManager
  // on Android, UIPrintInteractionController on iOS — the system "Save as
  // PDF"/AirPrint flow), falls back to window.print() in a browser.
  print() {
    const bridge = phpxNativeBridge();
    if (bridge && bridge.printPage) {
      bridge.printPage();
      return;
    }
    window.print();
  },

  // Native image picker (system gallery/file app — Android's
  // ActivityResultContracts.GetContent or iOS's PHPickerViewController),
  // falls back to a plain <input type=file> + FileReader in a browser.
  // hiddenFieldId (optional) also gets the data URL, so the picked image
  // can be submitted as part of a normal Form POST (see ImagePicker service).
  pickImage(imgElementId, hiddenFieldId) {
    const el = document.getElementById(imgElementId);
    const setResult = (dataUrl) => {
      if (el && dataUrl) el.src = dataUrl;
      if (hiddenFieldId) {
        const hidden = document.getElementById(hiddenFieldId);
        if (hidden) hidden.value = dataUrl || '';
      }
    };

    const bridge = phpxNativeBridge();
    if (bridge && bridge.pickImage) {
      window.onNativeImagePicked = setResult;
      bridge.pickImage();
      return;
    }

    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = () => {
      const file = input.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = () => setResult(reader.result);
      reader.readAsDataURL(file);
    };
    input.click();
  },
};
