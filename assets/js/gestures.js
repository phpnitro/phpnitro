function phpxTouchDistance(touches) {
  const dx = touches[0].clientX - touches[1].clientX;
  const dy = touches[0].clientY - touches[1].clientY;
  return Math.hypot(dx, dy);
}

function phpxTouchAngle(touches) {
  const dx = touches[0].clientX - touches[1].clientX;
  const dy = touches[0].clientY - touches[1].clientY;
  return (Math.atan2(dy, dx) * 180) / Math.PI;
}

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
        if (e.touches.length === 1) {
          startX = e.changedTouches[0].clientX;
        }
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

    // Pinch (scale) and rotation both need a starting two-finger reference
    // (distance/angle at touchstart) and report the ratio/delta once at
    // touchend — a stateless server round-trip has nowhere to put a
    // continuously-updating live value, so this mirrors swipe: one action
    // fired at gesture end, not per-frame.
    const pinchAction = el.dataset.onPinch;
    const rotateAction = el.dataset.onRotate;

    if (pinchAction || rotateAction) {
      let startDistance = null;
      let startAngle = null;

      el.addEventListener('touchstart', (e) => {
        if (e.touches.length === 2) {
          startDistance = phpxTouchDistance(e.touches);
          startAngle = phpxTouchAngle(e.touches);
        }
      });

      el.addEventListener('touchmove', (e) => {
        if (e.touches.length === 2 && (startDistance !== null || startAngle !== null)) {
          e.preventDefault();
        }
      }, { passive: false });

      el.addEventListener('touchend', (e) => {
        if (startDistance === null && startAngle === null) {
          return;
        }

        if (e.touches.length === 0) {
          if (pinchAction && startDistance !== null && e.changedTouches.length === 2) {
            const endDistance = phpxTouchDistance(e.changedTouches);
            window.phpxNav.submitAction(pinchAction, { scale: (endDistance / startDistance).toFixed(3) });
          }

          if (rotateAction && startAngle !== null && e.changedTouches.length === 2) {
            const endAngle = phpxTouchAngle(e.changedTouches);
            window.phpxNav.submitAction(rotateAction, { angle: (endAngle - startAngle).toFixed(2) });
          }

          startDistance = null;
          startAngle = null;
        }
      });
    }
  });
}

// Bound once at page load, then again after every partial-navigation swap
// (see nav.js) — document.body.innerHTML replacement creates entirely new
// elements, none of which carry the listeners bound here the first time.
document.addEventListener('DOMContentLoaded', phpxBindGestureAreas);
document.addEventListener('phpx:navigated', phpxBindGestureAreas);
