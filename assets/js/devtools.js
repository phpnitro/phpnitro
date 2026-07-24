// DevTools overlay (see Engine\PageRenderer::devToolsData/devToolsPanel) —
// only injected when APP_DEBUG=true, never shipped in a release build. A
// basic Flutter DevTools/React Native inspector equivalent: current route,
// render time, peak memory, PHP version, the current screen's state, and
// Preferences (when that package is installed) — in a small collapsible
// panel pinned above the bottom-left corner. No live widget-tree inspection
// (this app has no client-side widget tree — every screen is server-
// rendered HTML) — this is a request/response + state readout, not a
// runtime inspector.
//
// Pinned bottom-LEFT, not bottom-right: BottomNavigation's "pills" variant
// (packages/ui/src/BottomNavigation.php) floats at bottom-3/right-3 across
// the full width, and FloatingActionButton already claims bottom-20/right-4
// — a bottom-right devtools toggle collided with both (confirmed on a real
// device: the toggle sat directly on top of the nav bar's last item).
//
// Refreshes on every nav.js swap (not just the first full page load):
// PageRenderer now sends the same data under payload.devtools on partial
// responses too, and nav.js writes it into #phpx-devtools-root's dataset
// before dispatching phpx:navigated — see nav.js's applyPayload(). Without
// that, this panel would show only the very first route forever, which is
// exactly the bug this was rewritten to fix.
(function () {
  // Module-level, not per-render: re-render happens on every navigation,
  // but the developer's open/closed choice should survive that — without
  // this the panel would silently snap shut on every route change.
  let panelOpen = false;

  function renderEntries(obj) {
    if (!obj || typeof obj !== 'object') return '<div class="phpx-devtools-empty">—</div>';
    const keys = Object.keys(obj);
    if (keys.length === 0) return '<div class="phpx-devtools-empty">—</div>';

    return keys
      .map((key) => '<div><strong>' + key + '</strong> ' + JSON.stringify(obj[key]) + '</div>')
      .join('');
  }

  function render(root) {
    let data;
    try {
      data = JSON.parse(root.dataset.phpxDevtools || '{}');
    } catch (e) {
      return;
    }

    root.innerHTML =
      '<div id="phpx-devtools-panel" style="display:' + (panelOpen ? 'block' : 'none') + ';position:fixed;bottom:8rem;left:1rem;z-index:9999;' +
      'background:#111827;color:#e5e7eb;font:12px/1.4 monospace;padding:0.75rem 1rem;border-radius:0.5rem;' +
      'box-shadow:0 4px 12px rgba(0,0,0,0.3);min-width:200px;max-width:70vw;max-height:60vh;overflow:auto;">' +
      '<div><strong>route</strong> ' + data.path + '</div>' +
      '<div><strong>theme</strong> ' + data.theme + '</div>' +
      '<div><strong>render</strong> ' + data.renderMs + ' ms</div>' +
      '<div><strong>memory</strong> ' + data.memoryKb + ' KB</div>' +
      '<div><strong>php</strong> ' + data.phpVersion + '</div>' +
      '<div style="margin-top:0.5rem;border-top:1px solid #374151;padding-top:0.5rem;"><strong>state (' + data.path + ')</strong></div>' +
      renderEntries(data.state) +
      (data.preferences !== null && data.preferences !== undefined
        ? '<div style="margin-top:0.5rem;border-top:1px solid #374151;padding-top:0.5rem;"><strong>preferences</strong></div>' + renderEntries(data.preferences)
        : '') +
      '</div>' +
      '<button id="phpx-devtools-toggle" aria-label="DevTools" style="position:fixed;bottom:5rem;left:1rem;' +
      'z-index:9999;width:2.5rem;height:2.5rem;border-radius:9999px;border:none;background:#111827;color:#e5e7eb;' +
      'font:12px monospace;cursor:pointer;">&lt;/&gt;</button>';

    const toggle = document.getElementById('phpx-devtools-toggle');
    const panel = document.getElementById('phpx-devtools-panel');
    toggle.addEventListener('click', () => {
      panelOpen = !panelOpen;
      panel.style.display = panelOpen ? 'block' : 'none';
    });
  }

  function mount() {
    const root = document.getElementById('phpx-devtools-root');
    if (root) render(root);
  }

  document.addEventListener('DOMContentLoaded', mount);
  document.addEventListener('phpx:navigated', mount);
})();
