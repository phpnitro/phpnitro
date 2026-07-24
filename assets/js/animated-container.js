// AnimatedContainer (see Engine\AnimatedContainer) — FLIP technique: snapshot
// computed style before a swap, freeze the new element at those old values,
// then release to its own (already server-rendered) target style with a
// transition. Approximates Flutter's AnimatedContainer without any
// client-side reactivity/diffing layer — see the PHP class's docblock.
(function () {
  const ANIMATABLE_PROPS = ['backgroundColor', 'width', 'height', 'borderRadius', 'padding', 'opacity'];
  let snapshots = new Map();

  function snapshot(root) {
    snapshots = new Map();
    root.querySelectorAll('[data-animated-container]').forEach((el) => {
      const key = el.dataset.animatedContainer;
      const computed = getComputedStyle(el);
      const values = {};
      ANIMATABLE_PROPS.forEach((prop) => { values[prop] = computed[prop]; });
      snapshots.set(key, values);
    });
  }

  function play() {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.querySelectorAll('[data-animated-container]').forEach((el) => {
      const key = el.dataset.animatedContainer;
      const old = snapshots.get(key);
      if (!old || reducedMotion) return;

      const duration = el.dataset.duration || '300';
      const curve = el.dataset.curve || 'ease-in-out';

      // Freeze at the old values first — no transition yet, so this must
      // not animate.
      el.style.transition = 'none';
      ANIMATABLE_PROPS.forEach((prop) => { el.style[prop] = old[prop]; });

      // Force a reflow so the browser commits the frozen state above
      // before the transition is enabled — without this it would just
      // see the start and end style in the same frame and skip straight
      // to the end value.
      void el.offsetHeight;

      el.style.transition = `background-color ${duration}ms ${curve}, width ${duration}ms ${curve}, `
        + `height ${duration}ms ${curve}, border-radius ${duration}ms ${curve}, padding ${duration}ms ${curve}, `
        + `opacity ${duration}ms ${curve}`;
      ANIMATABLE_PROPS.forEach((prop) => { el.style.removeProperty(toCssProp(prop)); });

      window.setTimeout(() => {
        el.style.removeProperty('transition');
      }, Number(duration) + 50);
    });

    snapshots = new Map();
  }

  function toCssProp(camelCase) {
    return camelCase.replace(/[A-Z]/g, (letter) => '-' + letter.toLowerCase());
  }

  document.addEventListener('phpx:beforeSwap', (event) => snapshot(event.detail.root));
  document.addEventListener('phpx:navigated', play);
})();
