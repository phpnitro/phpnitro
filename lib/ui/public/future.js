(function () {
  function resolve(el) {
    const endpoint = el.dataset.futureEndpoint;

    fetch(endpoint)
      .then((r) => r.text())
      .then((html) => {
        el.innerHTML = html;
      })
      .catch(() => {
        el.innerHTML = '<p class="text-red-600 dark:text-red-400">Erreur de chargement.</p>';
      });
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-future-endpoint]').forEach(resolve);
  });
})();
