(function () {
  function poll(el) {
    const endpoint = el.dataset.streamEndpoint;
    const interval = parseInt(el.dataset.streamInterval || '2000', 10);

    fetch(endpoint)
      .then((r) => r.text())
      .then((html) => {
        el.innerHTML = html;
      })
      .catch(() => {})
      .finally(() => setTimeout(() => poll(el), interval));
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-stream-endpoint]').forEach(poll);
  });
})();
