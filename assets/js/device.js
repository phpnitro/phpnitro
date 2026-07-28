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
    if (!el) return;
    if (!navigator.mediaDevices) {
      el.insertAdjacentText('afterend', "Caméra indisponible (navigator.mediaDevices absent — contexte non sécurisé ou WebView trop ancienne).");
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

  // Prefers the native bridge (MediaRecorder, see WebAppInterface.kt's
  // recordAudioClip) FIRST — confirmed live on a real device that
  // getUserMedia({audio:true}) fails with "Could not start audio source"
  // even with RECORD_AUDIO already granted, a WebView/Chromium audio
  // capture limitation on some OEM builds. Every other capability here
  // already tries the native bridge before the Web API; the microphone
  // was the one exception, which is exactly why it silently didn't work.
  // Records durationMs of real audio and plays it back — proof the mic
  // genuinely works, not just a permission/API-availability check.
  recordAudioClip(outputElementId, durationMs = 3000) {
    const el = document.getElementById(outputElementId);
    if (!el) return;

    const bridge = phpxNativeBridge();
    if (bridge && bridge.recordAudioClip) {
      el.textContent = `Enregistrement (${(durationMs / 1000).toFixed(1)} s)...`;
      window.onNativeAudioRecorded = (dataUrl, error) => {
        if (!dataUrl) {
          el.textContent = 'Erreur micro : ' + (error || 'inconnue');
          return;
        }
        el.textContent = 'Micro activé — lecture de l’enregistrement';
        new Audio(dataUrl).play().catch(() => {});
      };
      bridge.recordAudioClip(durationMs);
      return;
    }

    this.openMicrophone(outputElementId);
  },

  // Fallback for browser testing / a native shell without this bridge
  // method (e.g. iOS before it grows a matching one) — the plain Web API,
  // known unreliable in some Android WebView builds (see recordAudioClip
  // above), kept only as a last resort now instead of the first attempt.
  async openMicrophone(outputElementId) {
    const el = document.getElementById(outputElementId);
    if (!el) return;
    if (!navigator.mediaDevices) {
      el.textContent = 'Micro indisponible (navigator.mediaDevices absent — contexte non sécurisé ou WebView trop ancienne).';
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

  // Phase 7 of docs/proposals/moteur-rendu-natif.md — opens the native
  // render engine screen from inside the real app (SettingsPage.php's
  // flag-gated button), not just via adb. No fallback: this is an
  // Android-only native Canvas screen, not something a browser tab or
  // WebView page could ever show on its own.
  openNativeRenderPreview() {
    const bridge = phpxNativeBridge();
    if (bridge && bridge.openNativeRenderPreview) {
      bridge.openNativeRenderPreview();
    }
  },

  // android_alarm_manager_plus equivalent — no web/browser fallback
  // exists (a page can't schedule work after it's been closed), so this
  // is a silent no-op outside a native shell.
  scheduleAlarm(requestCode, delaySeconds, title, message) {
    const bridge = phpxNativeBridge();
    if (bridge && bridge.scheduleAlarm) {
      bridge.scheduleAlarm(requestCode, delaySeconds, title, message);
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

  // Opens any URI (https://, tel:, mailto:, sms:) via the system handler
  // app — a plain WebView has no dialer/mail client of its own to hand
  // tel:/mailto: off to, and a bare <a> would just fail silently (see
  // MainActivity.kt's shouldOverrideUrlLoading for the non-JS-triggered
  // equivalent). window.open in a real browser tab already knows how to
  // do this itself.
  launchUrl(url) {
    const bridge = phpxNativeBridge();
    if (bridge && bridge.launchUrl) {
      bridge.launchUrl(url);
      return;
    }
    window.open(url, '_blank');
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

  // Sensor type constants mirror Android's Sensor.TYPE_* (1/4/2) so a
  // single window.onNativeSensorReading callback works for all three
  // without either side needing a name<->int translation table.
  SENSOR_ACCELEROMETER: 1,
  SENSOR_GYROSCOPE: 4,
  SENSOR_MAGNETIC_FIELD: 2,

  // No web fallback: browsers don't expose raw accelerometer/gyroscope/
  // magnetometer streams the way DeviceMotionEvent approximates only the
  // first, and inconsistently across browsers — native-only capability.
  startSensor(sensorType, outputElementId) {
    const el = document.getElementById(outputElementId);
    const bridge = phpxNativeBridge();
    if (!bridge || !bridge.startSensor) {
      if (el) el.textContent = 'Capteur indisponible (aucun pont natif).';
      return;
    }
    window.onNativeSensorReading = (type, x, y, z) => {
      if (type === sensorType && el) {
        el.textContent = `x=${x.toFixed(2)} y=${y.toFixed(2)} z=${z.toFixed(2)}`;
      }
    };
    bridge.startSensor(sensorType);
  },

  stopSensor(sensorType) {
    const bridge = phpxNativeBridge();
    if (bridge && bridge.stopSensor) bridge.stopSensor(sensorType);
  },

  // NFC is push-based: startNfc() just arms native foreground dispatch,
  // the actual tag arrives later via window.onNativeNfcTag whenever a scan
  // happens (see MainActivity.kt's handleNfcIntent).
  startNfc(outputElementId) {
    const el = document.getElementById(outputElementId);
    const bridge = phpxNativeBridge();
    if (!bridge || !bridge.startNfc) {
      if (el) el.textContent = 'NFC indisponible (aucun pont natif).';
      return;
    }
    window.onNativeNfcTag = (json) => {
      if (!el) return;
      try {
        const tag = JSON.parse(json);
        el.textContent = `tag ${tag.id}${tag.text ? ': ' + tag.text : ''}`;
      } catch (e) {
        el.textContent = 'Tag lu (contenu illisible).';
      }
    };
    if (el) el.textContent = 'En attente d\'un tag NFC...';
    bridge.startNfc();
  },

  stopNfc() {
    const bridge = phpxNativeBridge();
    if (bridge && bridge.stopNfc) bridge.stopNfc();
  },

  // Play Billing round-trip: queryProducts() asks native to fetch details
  // for a list of SKUs and writes a plain-text summary into outputElementId
  // itself (native side, not this JS) since the query is async and this
  // bridge has no promise-based call convention yet — see WebAppInterface.kt.
  queryProducts(productIds, outputElementId) {
    const bridge = phpxNativeBridge();
    if (!bridge || !bridge.queryProducts) {
      const el = document.getElementById(outputElementId);
      if (el) el.textContent = 'Achats intégrés indisponibles (aucun pont natif).';
      return;
    }
    bridge.queryProducts(JSON.stringify(productIds), outputElementId);
  },

  purchaseProduct(productId) {
    const bridge = phpxNativeBridge();
    if (bridge && bridge.purchaseProduct) bridge.purchaseProduct(productId);
  },

  addGeofence(id, latitude, longitude, radiusMeters) {
    const bridge = phpxNativeBridge();
    if (bridge && bridge.addGeofence) bridge.addGeofence(id, latitude, longitude, radiusMeters);
  },

  removeGeofence(id) {
    const bridge = phpxNativeBridge();
    if (bridge && bridge.removeGeofence) bridge.removeGeofence(id);
  },

  // Returns the new state (true = on) — no web fallback, browsers have no
  // torch API outside a getUserMedia video track's ImageCapture, itself
  // spotty support.
  toggleTorch() {
    const bridge = phpxNativeBridge();
    return bridge && bridge.toggleTorch ? bridge.toggleTorch() : false;
  },

  setScreenBrightness(level) {
    const bridge = phpxNativeBridge();
    if (bridge && bridge.setScreenBrightness) bridge.setScreenBrightness(level);
  },

  getBatteryLevel() {
    const bridge = phpxNativeBridge();
    if (bridge && bridge.getBatteryLevel) return bridge.getBatteryLevel();
    // Battery Status API is deprecated/removed from most browsers; no
    // reliable web fallback left.
    return null;
  },

  showBatteryLevel(outputElementId) {
    const el = document.getElementById(outputElementId);
    if (!el) return;
    const level = this.getBatteryLevel();
    el.textContent = level === null ? 'Batterie indisponible' : `${level}%`;
  },

  getDeviceId() {
    const bridge = phpxNativeBridge();
    return bridge && bridge.getDeviceId ? bridge.getDeviceId() : null;
  },

  showDeviceId(outputElementId) {
    const el = document.getElementById(outputElementId);
    if (el) el.textContent = this.getDeviceId() || 'Identifiant indisponible';
  },

  getBluetoothState() {
    const bridge = phpxNativeBridge();
    return bridge && bridge.getBluetoothState ? bridge.getBluetoothState() : 'unsupported';
  },

  getBondedBluetoothDevices() {
    const bridge = phpxNativeBridge();
    if (!bridge || !bridge.getBondedBluetoothDevices) return [];
    try {
      return JSON.parse(bridge.getBondedBluetoothDevices());
    } catch {
      return [];
    }
  },

  showBluetoothInfo(outputElementId) {
    const el = document.getElementById(outputElementId);
    if (!el) return;
    const state = this.getBluetoothState();
    const devices = this.getBondedBluetoothDevices();
    el.textContent = `${state} — ${devices.length} appareil(s) apparié(s)`;
  },

  // Android Keystore-backed encrypted storage (see WebAppInterface.kt's
  // EncryptedSharedPreferences use) — for tokens that shouldn't sit in
  // Engine\Preferences\'s plain SQLite table. No web fallback: there's no
  // equivalent-strength encrypted store in a browser context, so this is
  // silently a no-op/empty read outside a native shell rather than a fake
  // "secure" storage backed by plain localStorage.
  secureStore(key, value) {
    const bridge = phpxNativeBridge();
    if (bridge && bridge.secureStore) bridge.secureStore(key, value);
  },

  secureRetrieve(key) {
    const bridge = phpxNativeBridge();
    return bridge && bridge.secureRetrieve ? bridge.secureRetrieve(key) : null;
  },

  showSecureValue(key, outputElementId) {
    const el = document.getElementById(outputElementId);
    if (el) el.textContent = this.secureRetrieve(key) || '(vide)';
  },

  secureRemove(key) {
    const bridge = phpxNativeBridge();
    if (bridge && bridge.secureRemove) bridge.secureRemove(key);
  },

  getContacts() {
    const bridge = phpxNativeBridge();
    if (!bridge || !bridge.getContacts) return [];
    try {
      return JSON.parse(bridge.getContacts());
    } catch {
      return [];
    }
  },

  listContacts(outputElementId) {
    const el = document.getElementById(outputElementId);
    if (!el) return;
    const contacts = this.getContacts();
    el.textContent = contacts.length === 0
      ? 'Aucun contact (permission refusée ou liste vide)'
      : contacts.slice(0, 5).map((c) => `${c.name}: ${c.phone}`).join(', ');
  },

  getUpcomingEvents() {
    const bridge = phpxNativeBridge();
    if (!bridge || !bridge.getUpcomingEvents) return [];
    try {
      return JSON.parse(bridge.getUpcomingEvents());
    } catch {
      return [];
    }
  },

  listUpcomingEvents(outputElementId) {
    const el = document.getElementById(outputElementId);
    if (!el) return;
    const events = this.getUpcomingEvents();
    el.textContent = events.length === 0
      ? 'Aucun événement (permission refusée ou agenda vide)'
      : events.slice(0, 5).map((e) => e.title).join(', ');
  },

  scheduleBackgroundTask(endpoint, intervalMinutes) {
    const bridge = phpxNativeBridge();
    if (bridge && bridge.scheduleBackgroundTask) bridge.scheduleBackgroundTask(endpoint, intervalMinutes);
  },

  cancelBackgroundTask() {
    const bridge = phpxNativeBridge();
    if (bridge && bridge.cancelBackgroundTask) bridge.cancelBackgroundTask();
  },
};
