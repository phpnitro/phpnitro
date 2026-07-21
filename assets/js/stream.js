(function () {
  // Keyed by element rather than a dataset string: comparing raw fetched
  // HTML avoids re-diffing the DOM, and keeps arbitrarily large fragment
  // markup out of a data-* attribute.
  const lastHtml = new WeakMap();

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
        // Skip the re-render entirely when the poll returns identical
        // markup — otherwise every tick would re-trigger .phpx-animate's
        // keyframe (see future.js) even though nothing actually changed,
        // which would read as a distracting flicker rather than a
        // meaningful update.
        if (document.body.contains(el) && lastHtml.get(el) !== html) {
          lastHtml.set(el, html);
          el.innerHTML = '<div class="phpx-animate">' + html + '</div>';
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
