window.phpxDevice = {
  vibrate(ms) {
    // Prefer the genuinely-native path (Android Vibrator via WebAppInterface)
    // when running inside our Android shell; fall back to the standard Web
    // API (still real hardware, but WebView-mediated) everywhere else.
    if (window.AndroidNative && window.AndroidNative.vibrate) {
      window.AndroidNative.vibrate(ms);
      return;
    }
    if (navigator.vibrate) {
      navigator.vibrate(ms);
    }
  },

  takeNativePhoto(imgElementId) {
    if (!window.AndroidNative || !window.AndroidNative.takeNativePhoto) {
      const el = document.getElementById(imgElementId);
      if (el) el.insertAdjacentText('afterend', 'Photo native disponible uniquement dans la coquille Android.');
      return;
    }

    window.onNativePhotoTaken = (dataUrl) => {
      const el = document.getElementById(imgElementId);
      if (el && dataUrl) {
        el.src = dataUrl;
      }
    };

    window.AndroidNative.takeNativePhoto();
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

  // Fingerprint/face unlock. Prefers the native bridge (Android BiometricPrompt,
  // via WebAppInterface.showBiometricPrompt) since WebView does NOT implement
  // WebAuthn/FIDO2 platform authenticators the way the Chrome app does —
  // navigator.credentials is unreliable-to-absent inside a WebView even when
  // the device has a fingerprint enrolled. Falls back to WebAuthn only when
  // running in a real browser (e.g. testing the dev server directly).
  fingerprint(outputElementId) {
    const el = document.getElementById(outputElementId);

    if (window.AndroidNative && window.AndroidNative.showBiometricPrompt) {
      window.onNativeBiometricResult = (success, message) => {
        if (el) el.textContent = success ? 'Authentification réussie ✓' : ('Échec : ' + message);
      };
      window.AndroidNative.showBiometricPrompt();
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
};
