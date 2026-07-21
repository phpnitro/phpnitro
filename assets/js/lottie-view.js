// LottieView (see Engine\LottieView) — plays a bundled Lottie JSON
// animation via the vendored lottie-web (assets/js/vendor/lottie.min.js,
// loaded before this file). Fully offline: the animation JSON is fetched
// from the app's own served assets, not a CDN.
(function () {
  function mount(el) {
    if (el.dataset.lottieMounted || !window.lottie) return;
    el.dataset.lottieMounted = '1';

    window.lottie.loadAnimation({
      container: el,
      renderer: 'svg',
      loop: el.dataset.loop !== '0',
      autoplay: el.dataset.autoplay !== '0',
      path: el.dataset.src,
    });
  }

  function mountAll() {
    document.querySelectorAll('[data-lottie-view]').forEach(mount);
  }

  document.addEventListener('DOMContentLoaded', mountAll);
  document.addEventListener('phpx:navigated', mountAll);
})();
