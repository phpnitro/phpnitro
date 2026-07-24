// Hero (see Engine\Hero) — FLIP technique on getBoundingClientRect() instead
// of computed style (animated-container.js's approach): record where the
// tagged element sits on screen before a swap, then make the newly-inserted
// element with the same tag instantly LOOK like it's still there via a CSS
// transform, before releasing to transform:none with a transition so the
// browser flies it to its real position/size.
(function () {
  let rects = new Map();

  function snapshot(root) {
    rects = new Map();
    root.querySelectorAll('[data-hero]').forEach((el) => {
      rects.set(el.dataset.hero, el.getBoundingClientRect());
    });
  }

  function play() {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.querySelectorAll('[data-hero]').forEach((el) => {
      const oldRect = rects.get(el.dataset.hero);
      if (!oldRect || reducedMotion) return;

      const newRect = el.getBoundingClientRect();
      if (newRect.width === 0 || newRect.height === 0) return;

      const dx = oldRect.left - newRect.left;
      const dy = oldRect.top - newRect.top;
      const sx = oldRect.width / newRect.width;
      const sy = oldRect.height / newRect.height;

      const duration = el.dataset.duration || '300';
      const curve = el.dataset.curve || 'ease-in-out';

      el.style.transformOrigin = 'top left';
      el.style.transition = 'none';
      el.style.transform = `translate(${dx}px, ${dy}px) scale(${sx}, ${sy})`;

      void el.offsetHeight;

      el.style.transition = `transform ${duration}ms ${curve}`;
      el.style.transform = 'none';

      window.setTimeout(() => {
        el.style.removeProperty('transition');
        el.style.removeProperty('transform');
        el.style.removeProperty('transform-origin');
      }, Number(duration) + 50);
    });

    rects = new Map();
  }

  document.addEventListener('phpx:beforeSwap', (event) => snapshot(event.detail.root));
  document.addEventListener('phpx:navigated', play);
})();
