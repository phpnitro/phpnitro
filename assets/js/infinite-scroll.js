// InfiniteScrollList (see Engine\InfiniteScrollList) — infinite_scroll_pagination
// equivalent. A sentinel element at the bottom of the list triggers loading
// the next page (via IntersectionObserver) from data-endpoint?page=N,
// appending the returned HTML. Stops once a fetch returns empty body (the
// consuming app's endpoint signals "no more pages" this way, no separate
// "hasMore" flag/contract needed).
(function () {
  function bind(sentinel) {
    if (sentinel.dataset.infiniteScrollBound) return;
    sentinel.dataset.infiniteScrollBound = '1';

    const list = sentinel.closest('[data-infinite-scroll-list]');
    const endpoint = list.dataset.endpoint;
    let page = 1;
    let loading = false;
    let done = false;

    const observer = new IntersectionObserver((entries) => {
      if (!entries[0].isIntersecting || loading || done) return;
      loading = true;
      page += 1;

      fetch(`${endpoint}${endpoint.includes('?') ? '&' : '?'}page=${page}`)
        .then((r) => r.text())
        .then((html) => {
          if (html.trim() === '') {
            done = true;
            observer.disconnect();
            return;
          }
          sentinel.insertAdjacentHTML('beforebegin', html);
        })
        .catch(() => {
          done = true;
          observer.disconnect();
        })
        .finally(() => {
          loading = false;
        });
    }, { rootMargin: '200px' });

    observer.observe(sentinel);
  }

  function bindAll() {
    document.querySelectorAll('[data-infinite-scroll-sentinel]').forEach(bind);
  }

  document.addEventListener('DOMContentLoaded', bindAll);
  document.addEventListener('phpx:navigated', bindAll);
})();
