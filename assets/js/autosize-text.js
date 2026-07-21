// AutoSizeText (see Engine\AutoSizeText) — shrinks font-size step by step
// until the text fits its container without wrapping/overflowing, down to
// a minimum. Re-measures on resize and after every nav.js swap.
(function () {
  function fit(el) {
    const min = parseFloat(el.dataset.minSize || '10');
    const max = parseFloat(el.dataset.maxSize || '32');
    let size = max;
    el.style.fontSize = size + 'px';

    while (size > min && (el.scrollWidth > el.clientWidth || el.scrollHeight > el.clientHeight)) {
      size -= 1;
      el.style.fontSize = size + 'px';
    }
  }

  function fitAll() {
    document.querySelectorAll('[data-autosize-text]').forEach(fit);
  }

  document.addEventListener('DOMContentLoaded', fitAll);
  document.addEventListener('phpx:navigated', fitAll);
  window.addEventListener('resize', fitAll);
})();
