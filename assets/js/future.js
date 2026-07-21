(function () {
  function resolve(el) {
    const endpoint = el.dataset.futureEndpoint;

    fetch(endpoint)
      .then((r) => r.text())
      .then((html) => {
        // .phpx-animate (see assets/css/input.css) is FadeIn's own class,
        // reused here as-is (its CSS custom properties fall back to
        // sensible defaults with none set inline) — a resolved future is a
        // one-shot reveal, so there's no repeat-animation risk to gate
        // against the way stream.js's recurring poll has to.
        if (document.body.contains(el)) {
          el.innerHTML = '<div class="phpx-animate">' + html + '</div>';
        }
      })
      .catch(() => {
        if (document.body.contains(el)) {
          el.innerHTML = '<p class="text-red-600 dark:text-red-400">Erreur de chargement.</p>';
        }
      });
  }

  function bindFutureBuilders() {
    document.querySelectorAll('[data-future-endpoint]').forEach(resolve);
  }

  document.addEventListener('DOMContentLoaded', bindFutureBuilders);
  document.addEventListener('phpx:navigated', bindFutureBuilders);
})();
