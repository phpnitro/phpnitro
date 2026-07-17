function phpxBindGestureAreas() {
  document.querySelectorAll('.gesture-area').forEach((el) => {
    const dblClickAction = el.dataset.onDblclick;
    if (dblClickAction) {
      el.addEventListener('dblclick', () => window.phpxNav.submitAction(dblClickAction));
    }

    const swipeLeftAction = el.dataset.onSwipeLeft;
    const swipeRightAction = el.dataset.onSwipeRight;

    if (swipeLeftAction || swipeRightAction) {
      let startX = null;

      el.addEventListener('touchstart', (e) => {
        startX = e.changedTouches[0].clientX;
      });

      el.addEventListener('touchend', (e) => {
        if (startX === null) {
          return;
        }

        const deltaX = e.changedTouches[0].clientX - startX;
        const threshold = 40;

        if (deltaX <= -threshold && swipeLeftAction) {
          window.phpxNav.submitAction(swipeLeftAction);
        } else if (deltaX >= threshold && swipeRightAction) {
          window.phpxNav.submitAction(swipeRightAction);
        }

        startX = null;
      });
    }
  });
}

// Bound once at page load, then again after every partial-navigation swap
// (see nav.js) — document.body.innerHTML replacement creates entirely new
// elements, none of which carry the listeners bound here the first time.
document.addEventListener('DOMContentLoaded', phpxBindGestureAreas);
document.addEventListener('phpx:navigated', phpxBindGestureAreas);
