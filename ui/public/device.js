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
};
