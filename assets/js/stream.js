(function () {
  function poll(el) {
    // A partial-navigation swap (see nav.js) can detach this element from
    // the DOM while a poll cycle is still in flight — without this check,
    // the setTimeout chain would keep polling forever for an element
    // nobody can see anymore.
    if (!document.body.contains(el)) {
      return;
    }

    const endpoint = el.dataset.streamEndpoint;
    const interval = parseInt(el.dataset.streamInterval || '2000', 10);

    fetch(endpoint)
      .then((r) => r.text())
      .then((html) => {
        if (document.body.contains(el)) {
          el.innerHTML = html;
        }
      })
      .catch(() => {})
      .finally(() => setTimeout(() => poll(el), interval));
  }

  function bindStreamBuilders() {
    document.querySelectorAll('[data-stream-endpoint]').forEach(poll);
  }

  document.addEventListener('DOMContentLoaded', bindStreamBuilders);
  document.addEventListener('phpx:navigated', bindStreamBuilders);
})();
