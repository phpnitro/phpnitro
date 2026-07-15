document.addEventListener('DOMContentLoaded', () => {
  function sendAction(action) {
    const body = new URLSearchParams();
    body.set('_action', action);
    fetch(window.location.pathname, { method: 'POST', body })
      .then(() => window.location.reload());
  }

  document.querySelectorAll('.gesture-area').forEach((el) => {
    const dblClickAction = el.dataset.onDblclick;
    if (dblClickAction) {
      el.addEventListener('dblclick', () => sendAction(dblClickAction));
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
          sendAction(swipeLeftAction);
        } else if (deltaX >= threshold && swipeRightAction) {
          sendAction(swipeRightAction);
        }

        startX = null;
      });
    }
  });
});
