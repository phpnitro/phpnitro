(function () {
  function resolve(el) {
    const endpoint = el.dataset.futureEndpoint;

    fetch(endpoint)
      .then((r) => r.text())
      .then((html) => {
        if (document.body.contains(el)) {
          el.innerHTML = html;
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
