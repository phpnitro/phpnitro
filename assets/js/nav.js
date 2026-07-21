(function () {
  // Setting .innerHTML never executes embedded <script> tags — recreating
  // each one via createElement (and copying its attributes/content) does,
  // which is what lets gateway script tags (see Engine\Payments\Kkiapay
  // etc.) keep working after a partial swap instead of only on a real
  // page load.
  function executeScripts(container) {
    container.querySelectorAll('script').forEach((oldScript) => {
      const newScript = document.createElement('script');
      // A <script src=...> created via createElement defaults to
      // executing asynchronously (out of document order) unless this is
      // set — without it, a widget's own inline follow-up script (e.g.
      // OsmMap's L.map(...) call, right after leaflet.js's <script src>)
      // can run before the library it depends on has finished loading.
      newScript.async = false;
      for (const attr of oldScript.attributes) {
        newScript.setAttribute(attr.name, attr.value);
      }
      newScript.textContent = oldScript.textContent;
      oldScript.replaceWith(newScript);
    });
  }

  // The bottom nav lives OUTSIDE #phpx-content and is rendered once by
  // PageRenderer — it's never part of payload.html, so it's never
  // destroyed/recreated by the swap below (that full-node replacement is
  // what caused the visible jump before). Only two things about it ever
  // change per navigation: whether it's shown at all, and which tab looks
  // active — both handled here without touching the <nav> node itself.
  function updatePersistentNav(payload) {
    const wrapper = document.getElementById('phpx-bottom-nav-wrapper');
    if (wrapper) {
      wrapper.classList.toggle('hidden', !payload.showBottomNav);
    }

    const nav = document.getElementById('phpx-bottom-nav');
    if (!nav || !payload.path) return;

    const currentPath = (payload.path.split('?')[0] || '/').replace(/\/+$/, '') || '/';
    nav.querySelectorAll('a[data-active-class]').forEach((link) => {
      const linkPath = (new URL(link.getAttribute('href'), window.location.origin).pathname || '/').replace(/\/+$/, '') || '/';
      link.className = linkPath === currentPath ? link.dataset.activeClass : link.dataset.inactiveClass;
    });
  }

  function applyPayload(payload) {
    // A redirect target can be an external URL (e.g. a hosted Stripe
    // Checkout session) that can't be swapped in — go there for real.
    if (payload.redirect) {
      window.location.href = payload.redirect;
      return;
    }

    document.documentElement.classList.toggle('dark', payload.theme === 'dark');
    // Wrapped in a fresh element (rather than applying the class to the
    // persistent #phpx-content itself) so the .phpx-page-enter keyframe
    // animation — which only plays on insertion, not on a class being
    // re-applied to an existing node — actually fires on every navigation.
    const wrappedHtml = '<div class="phpx-page-enter">' + payload.html + '</div>';
    const content = document.getElementById('phpx-content');
    if (content) {
      content.innerHTML = wrappedHtml;
      executeScripts(content);
    } else {
      // Full pages rendered before this content wrapper existed (or a
      // fragment route with no chrome at all) — fall back to the whole body.
      document.body.innerHTML = wrappedHtml;
      executeScripts(document.body);
    }
    updatePersistentNav(payload);

    const current = window.location.pathname + window.location.search;
    if (payload.path && payload.path !== current) {
      history.pushState({ phpxPartial: true }, '', payload.path);
    }

    // Anything that binds to the DOM once at DOMContentLoaded (gestures,
    // StreamBuilder/FutureBuilder polling) needs a chance to re-bind against
    // the freshly-swapped-in elements — nothing here does that on its own.
    document.dispatchEvent(new CustomEvent('phpx:navigated'));
  }

  async function request(url, options) {
    let response;

    try {
      response = await fetch(url, {
        ...options,
        headers: { ...(options.headers || {}), 'X-Phpx-Partial': '1' },
      });
    } catch {
      window.location.href = url;
      return;
    }

    // Covers CSRF failures (419), 404s, 500s, and anything else that isn't
    // a real partial response — falls back to a real navigation so the
    // user sees the actual error page instead of a broken swap.
    const contentType = response.headers.get('content-type') || '';
    if (!response.ok || !contentType.includes('application/json')) {
      window.location.href = url;
      return;
    }

    applyPayload(await response.json());
  }

  function submitAction(action, extraFields) {
    const body = new URLSearchParams();
    body.set('_action', action);
    for (const [key, value] of Object.entries(extraFields || {})) {
      body.set(key, value);
    }
    const token = document.querySelector('meta[name="csrf-token"]');
    if (token) {
      body.set('_token', token.content);
    }

    return request(window.location.pathname, { method: 'POST', body });
  }

  function submitForm(form, action, extraFields) {
    const body = form ? new URLSearchParams(new FormData(form)) : new URLSearchParams();
    body.set('_action', action);
    for (const [key, value] of Object.entries(extraFields || {})) {
      body.set(key, value);
    }
    const token = document.querySelector('meta[name="csrf-token"]');
    if (token) {
      body.set('_token', token.content);
    }

    return request(window.location.pathname, { method: 'POST', body });
  }

  function onLinkClick(event) {
    const link = event.target.closest('a[href]');
    if (!link || link.target || link.hasAttribute('download')) return;
    if (link.origin !== window.location.origin) return;
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) return;

    event.preventDefault();
    request(link.href, { method: 'GET' });
  }

  function onFormSubmit(event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post') return;

    event.preventDefault();
    request(form.getAttribute('action') || window.location.pathname, {
      method: 'POST',
      body: new URLSearchParams(new FormData(form)),
    });
  }

  window.addEventListener('popstate', () => {
    request(window.location.href, { method: 'GET' });
  });

  document.addEventListener('DOMContentLoaded', () => {
    document.body.addEventListener('click', onLinkClick);
    document.body.addEventListener('submit', onFormSubmit);
  });

  window.phpxNav = { submitAction, submitForm };
})();
