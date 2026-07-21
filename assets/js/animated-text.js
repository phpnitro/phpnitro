// AnimatedText (see Engine\AnimatedText) — cycles through a list of
// strings with a typewriter effect (types, pauses, deletes, moves on),
// animated_text_kit's core idea. The full string list + timings are
// data-attributes set server-side; everything else is client-only, same
// as GestureDetector being the only other place this framework runs
// per-frame JS.
(function () {
  function startOne(el) {
    if (el.dataset.autoTextStarted) return;
    el.dataset.autoTextStarted = '1';

    let texts;
    try {
      texts = JSON.parse(el.dataset.texts || '[]');
    } catch {
      texts = [];
    }
    if (texts.length === 0) return;

    const typeSpeed = parseInt(el.dataset.typeSpeedMs || '60', 10);
    const pauseMs = parseInt(el.dataset.pauseMs || '1200', 10);
    const deleteSpeed = parseInt(el.dataset.deleteSpeedMs || '30', 10);

    let textIndex = 0;
    let charIndex = 0;
    let deleting = false;

    function tick() {
      const current = texts[textIndex];

      if (!deleting) {
        charIndex += 1;
        el.textContent = current.slice(0, charIndex);
        if (charIndex === current.length) {
          deleting = true;
          setTimeout(tick, pauseMs);
          return;
        }
        setTimeout(tick, typeSpeed);
        return;
      }

      charIndex -= 1;
      el.textContent = current.slice(0, charIndex);
      if (charIndex === 0) {
        deleting = false;
        textIndex = (textIndex + 1) % texts.length;
        setTimeout(tick, typeSpeed);
        return;
      }
      setTimeout(tick, deleteSpeed);
    }

    tick();
  }

  function startAll() {
    document.querySelectorAll('[data-animated-text]').forEach(startOne);
  }

  document.addEventListener('DOMContentLoaded', startAll);
  document.addEventListener('phpx:navigated', startAll);
})();
