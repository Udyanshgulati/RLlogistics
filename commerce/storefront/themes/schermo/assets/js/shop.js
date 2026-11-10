// Schermo shop: filters, sorting and pagination update in place.
// Progressive enhancement over the server-rendered listing: every control is still a real link or form, so direct
// visits, shared URLs and no-JS browsing work unchanged. With JS, the same URL is fetched and only the listing
// regions are swapped: header copy (title, count, collection links), toolbar (pills, chips, sorting), the filter
// drawer and the grid. The URL is kept in sync (Back/Forward work) and the scroll position is left alone.
(() => {
  'use strict';
  if (!window.fetch || !window.DOMParser || !history.pushState) return;
  const $ = (s, r = document) => r.querySelector(s);
  const grid = () => $('.sx-grid-wrap');
  if (!grid()) return;

  const REGIONS = ['.sx-copy', '.sx-bar', '#shop-filters', '.sx-grid-wrap', '.nav-primary', '#drawer-menu nav'];
  const FILTER_KEYS = ['type', 'collection', 'min_price', 'max_price', 'available', 'filter_size'];
  const reduce = matchMedia('(prefers-reduced-motion: reduce)');
  let controller = null;
  let seq = 0;

  // Same URL the server would redirect to: sizes joined with commas, empty fields dropped.
  const clean = (url) => {
    const u = new URL(url, location.href);
    const sizes = u.searchParams.getAll('filter_size[]');
    if (sizes.length) { u.searchParams.delete('filter_size[]'); u.searchParams.set('filter_size', sizes.join(',')); }
    FILTER_KEYS.forEach((k) => { if (u.searchParams.has(k) && u.searchParams.get(k) === '') u.searchParams.delete(k); });
    if (u.searchParams.get('filter_size')) u.searchParams.set('query_type_size', 'or'); else u.searchParams.delete('query_type_size');
    u.searchParams.delete('paged');
    u.pathname = u.pathname.replace(/\/page\/\d+\/?$/, '/'); // new filters start from page 1
    u.hash = '';
    return u;
  };

  const isListing = (u) => u.origin === location.origin && (/\/(shop|product-category)\//.test(u.pathname) || (u.searchParams.get('post_type') === 'product' && u.searchParams.has('s')));

  const setBusy = (on) => {
    const g = grid();
    if (!g) return;
    if (on) { g.style.minHeight = `${g.offsetHeight}px`; g.classList.add('is-loading'); g.setAttribute('aria-busy', 'true'); }
    else { g.classList.remove('is-loading'); g.removeAttribute('aria-busy'); requestAnimationFrame(() => { g.style.minHeight = ''; }); }
  };

  const showError = (url) => {
    const g = grid();
    setBusy(false);
    const el = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text) n.textContent = text; return n; };
    const box = el('div', 'empty-state sx-error');
    box.setAttribute('role', 'alert');
    const actions = el('p');
    const retry = el('button', 'btn', 'Try again');
    retry.type = 'button';
    retry.addEventListener('click', () => load(url, { push: false }));
    const clear = el('a', 'btn btn-outline', 'Clear filters');
    clear.href = location.pathname.replace(/\/page\/\d+\/?$/, '/');
    actions.append(retry, ' ', clear);
    box.append(el('p', 'eyebrow', 'Something went wrong'), el('h2', '', 'We could not load these results'), actions);
    g.replaceChildren(box);
  };

  async function load(url, { push = true } = {}) {
    const u = url instanceof URL ? url : new URL(url, location.href);
    if (controller) controller.abort();
    controller = new AbortController();
    const mine = ++seq;
    const dlg = $('#shop-filters');
    if (dlg && dlg.open) dlg.close();
    setBusy(true);
    if (push) history.pushState({ sx: true }, '', u.pathname + u.search);
    try {
      const res = await fetch(u.pathname + u.search, { signal: controller.signal, credentials: 'same-origin', headers: { 'X-Requested-With': 'schermo-shop' } });
      if (!res.ok && res.status !== 404) throw new Error(String(res.status));
      const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
      if (mine !== seq) return; // a newer request won
      if (!doc.querySelector('.sx-grid-wrap')) { location.assign(u.href); return; }
      const finalUrl = res.redirected ? new URL(res.url) : u;
      if (res.redirected) history.replaceState({ sx: true }, '', finalUrl.pathname + finalUrl.search);
      const swap = () => {
        if (mine !== seq) return; // superseded while waiting to paint
        REGIONS.forEach((sel) => {
          const next = doc.querySelector(sel);
          const cur = $(sel);
          if (next && cur) cur.replaceWith(next);
        });
        document.title = doc.title;
        const g = grid();
        g.classList.add('is-entering');
        setTimeout(() => g.classList.remove('is-entering'), 400);
        setBusy(false);
      };
      // Swap synchronously (the CSS fades the new cards in); view transitions can hold the callback back.
      swap();
    } catch (err) {
      if (err.name === 'AbortError' || mine !== seq) return;
      showError(u);
    }
  }

  // Links inside the listing controls (type pills, chips, clear all, collection links, pagination, empty state).
  document.addEventListener('click', (e) => {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const a = e.target.closest('.sx-bar a, .sx-colls a, .sx-sheet a, .woocommerce-pagination a, .sx-grid-wrap .empty-state a');
    if (!a || a.target === '_blank') return;
    const u = new URL(a.href, location.href);
    if (!isListing(u)) return;
    e.preventDefault();
    const keepPage = a.closest('.woocommerce-pagination');
    load(keepPage ? new URL(a.href, location.href) : clean(u));
    if (keepPage) $('.sx-bar-wrap')?.scrollIntoView({ behavior: reduce.matches ? 'auto' : 'smooth', block: 'start' });
  });

  // Filter drawer and sorting forms.
  const submitForm = (form) => {
    const u = new URL(form.getAttribute('action') || location.pathname, location.href);
    u.search = new URLSearchParams(new FormData(form)).toString();
    load(clean(u));
  };
  document.addEventListener('submit', (e) => {
    const form = e.target.closest('.sx-form, .woocommerce-ordering');
    if (!form) return;
    e.preventDefault();
    submitForm(form);
  });
  // WooCommerce submits the sort form on change with form.submit() (no submit event), so take the change first.
  document.addEventListener('change', (e) => {
    const sel = e.target.closest('.woocommerce-ordering select');
    if (!sel) return;
    e.stopPropagation();
    submitForm(sel.form);
  }, true);

  window.addEventListener('popstate', () => { if (isListing(new URL(location.href))) load(location.href, { push: false }); });
  history.replaceState({ sx: true }, '', location.href);
})();

// Product-type pills on phones: bring the selected type into view and drop the edge fade once the row is scrolled to
// its end, so it is clear when more types are hidden to the right.
(() => {
  'use strict';
  const setup = () => {
    const row = document.querySelector('.sx-cats');
    if (!row || row.dataset.scrollSetup) return;
    row.dataset.scrollSetup = '1';
    const update = () => row.classList.toggle('is-end', row.scrollLeft + row.clientWidth >= row.scrollWidth - 4);
    row.addEventListener('scroll', update, { passive: true });
    const on = row.querySelector('.is-on');
    if (on && on.offsetLeft + on.offsetWidth > row.clientWidth) row.scrollLeft = on.offsetLeft - 16;
    update();
  };
  setup();
  const wrap = document.querySelector('.sx-bar-wrap');
  if (wrap && window.MutationObserver) new MutationObserver(setup).observe(wrap, { childList: true, subtree: true });
})();
