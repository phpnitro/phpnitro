// Canvas (see Engine\Canvas) — replays a JSON array of draw ops
// (rect/circle/line/text) against CanvasRenderingContext2D once, at mount.
// No animation loop: this is a one-shot drawing, not a live CustomPaint.
(function () {
  function draw(ctx, op) {
    ctx.fillStyle = op.color;
    ctx.strokeStyle = op.color;

    switch (op.type) {
      case 'rect':
        ctx.fillRect(op.x, op.y, op.w, op.h);
        break;
      case 'circle':
        ctx.beginPath();
        ctx.arc(op.x, op.y, op.r, 0, Math.PI * 2);
        ctx.fill();
        break;
      case 'line':
        ctx.lineWidth = op.width;
        ctx.beginPath();
        ctx.moveTo(op.x1, op.y1);
        ctx.lineTo(op.x2, op.y2);
        ctx.stroke();
        break;
      case 'text':
        ctx.font = op.font;
        ctx.fillText(op.content, op.x, op.y);
        break;
    }
  }

  function mount(el) {
    if (el.dataset.canvasMounted) return;
    el.dataset.canvasMounted = '1';

    const ctx = el.getContext('2d');
    if (!ctx) return;

    let ops = [];
    try {
      ops = JSON.parse(el.dataset.ops || '[]');
    } catch (e) {
      return;
    }

    ops.forEach((op) => draw(ctx, op));
  }

  function mountAll() {
    document.querySelectorAll('[data-phpx-canvas]').forEach(mount);
  }

  document.addEventListener('DOMContentLoaded', mountAll);
  document.addEventListener('phpx:navigated', mountAll);
})();
