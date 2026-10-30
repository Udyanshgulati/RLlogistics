// Brandhouse — small progressive enhancements. Every feature works without JavaScript.
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

  // Mobile menu drawer.
  const drawer = $('#drawer-menu');
  $$('[data-menu-open]').forEach((b) => b.addEventListener('click', () => { drawer.showModal(); b.setAttribute('aria-expanded', 'true'); }));
  if (drawer) drawer.addEventListener('close', () => $$('[data-menu-open]').forEach((b) => b.setAttribute('aria-expanded', 'false')));

  // Dialogs: [data-dialog="id"] opens, [data-dialog-close] / backdrop click closes.
  document.addEventListener('click', (e) => {
    const open = e.target.closest('[data-dialog]');
    if (open) { const d = document.getElementById(open.dataset.dialog); if (d) d.showModal(); }
    const close = e.target.closest('[data-dialog-close]');
    if (close) close.closest('dialog').close();
    if (e.target instanceof HTMLDialogElement) e.target.close();
  });

  // Country/currency popup: opens on a first visit. Closing it without choosing keeps the detected market.
  const market = document.getElementById('market-picker');
  if (market && market.hasAttribute('data-autoopen')) {
    market.showModal();
    market.addEventListener('close', () => {
      if (document.cookie.includes('bh_market=')) return;
      const picked = market.querySelector('input[name=bh_market]:checked');
      if (picked) document.cookie = `bh_market=${picked.value}; path=/; max-age=31536000; samesite=lax${location.protocol === 'https:' ? '; secure' : ''}`;
    });
  }

  // Phone photo slider counter.
  $$('[data-swipe]').forEach((row) => {
    const out = row.parentElement.querySelector('[data-swipe-index]');
    row.addEventListener('scroll', () => { out.textContent = String(Math.round(row.scrollLeft / row.clientWidth) + 1); }, { passive: true });
  });

  // Drop countdown.
  $$('[data-deadline]').forEach((clock) => {
    const end = Number(clock.dataset.deadline);
    const set = (k, v) => { const el = clock.querySelector(`[data-u="${k}"]`); if (el) el.textContent = String(v).padStart(2, '0'); };
    const tick = () => {
      const left = Math.max(0, end - Date.now());
      set('d', Math.floor(left / 864e5)); set('h', Math.floor(left / 36e5) % 24); set('m', Math.floor(left / 6e4) % 60); set('s', Math.floor(left / 1e3) % 60);
      if (left === 0) { clearInterval(timer); clock.closest('.drop').hidden = true; }
    };
    const timer = setInterval(tick, 1000); tick();
  });

  // Free-shipping progress in the block cart, kept in step with the cart's own data store.
  const bar = $('.ship-bar');
  if (bar) window.addEventListener('load', () => {
    const data = window.wp && window.wp.data;
    if (!data || !data.select('wc/store/cart')) return;
    const min = Number(bar.dataset.freeMin);
    const render = () => {
      const t = data.select('wc/store/cart').getCartTotals();
      if (!t || t.total_items === undefined) return;
      const unit = 10 ** (t.currency_minor_unit || 0);
      const have = (Number(t.total_items) + Number(t.total_items_tax || 0)) / unit;
      const left = Math.max(0, min - have);
      $('.ship-fill', bar).style.width = `${Math.min(100, (have / min) * 100)}%`;
      $('.ship-msg', bar).textContent = left > 0
        ? bar.dataset.tplMore.replace('%s', bar.dataset.currency + left.toLocaleString(undefined, { maximumFractionDigits: 0 }))
        : bar.dataset.tplDone;
      bar.classList.toggle('is-done', left === 0);
    };
    render();
    data.subscribe(render, 'wc/store/cart');
  });

  // Search panel.
  const searchBtn = $('[data-search-open]');
  const panel = $('#site-search');
  if (searchBtn && panel) searchBtn.addEventListener('click', () => {
    panel.hidden = !panel.hidden;
    searchBtn.setAttribute('aria-expanded', String(!panel.hidden));
    if (!panel.hidden) $('input[type=search]', panel).focus();
  });

  // Quantity steppers around WooCommerce's number input.
  $$('.quantity').forEach((q) => {
    const input = $('input.qty', q);
    if (!input || input.type === 'hidden' || q.dataset.enhanced) return;
    q.dataset.enhanced = '1';
    const step = (d) => {
      const min = parseFloat(input.min) || 1;
      const max = parseFloat(input.max) || Infinity;
      const v = Math.min(max, Math.max(min, (parseFloat(input.value) || min) + d));
      input.value = v;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    };
    const mk = (label, d) => { const b = document.createElement('button'); b.type = 'button'; b.className = 'qty-btn'; b.textContent = label; b.setAttribute('aria-label', d < 0 ? 'Decrease quantity' : 'Increase quantity'); b.addEventListener('click', () => step(d)); return b; };
    q.prepend(mk('−', -1));
    q.append(mk('+', 1));
  });

  // Size buttons for WooCommerce's size <select>. The select stays the source of truth for WooCommerce's
  // variation script; buttons only set it. Availability comes from the product's real variation data.
  const form = $('form.variations_form');
  const attr = (window.brandhouse && window.brandhouse.sizeAttribute) || 'attribute_pa_size';
  const select = form && form.querySelector(`select[name="${attr}"]`);
  if (form && select) {
    let variations = [];
    try { variations = JSON.parse(form.dataset.product_variations || '[]'); } catch { variations = []; }
    const inStock = (value) => variations === false || variations.length === 0 ? true
      : variations.some((v) => (v.attributes[attr] === value || v.attributes[attr] === '') && v.is_in_stock);
    const group = document.createElement('div');
    group.className = 'size-options';
    group.setAttribute('role', 'radiogroup');
    group.setAttribute('aria-label', 'Size');
    Array.from(select.options).filter((o) => o.value).forEach((o) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'size-opt';
      b.textContent = o.textContent;
      b.dataset.value = o.value;
      b.setAttribute('role', 'radio');
      b.setAttribute('aria-checked', String(select.value === o.value));
      if (!inStock(o.value)) { b.disabled = true; b.setAttribute('aria-label', `${o.textContent} — sold out`); }
      b.addEventListener('click', () => {
        select.value = o.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        if (window.jQuery) window.jQuery(select).trigger('change');
      });
      group.appendChild(b);
    });
    select.classList.add('is-enhanced');
    select.setAttribute('tabindex', '-1');
    select.setAttribute('aria-hidden', 'true');
    select.insertAdjacentElement('afterend', group);
    const sync = () => $$('.size-opt', group).forEach((b) => b.setAttribute('aria-checked', String(b.dataset.value === select.value)));
    select.addEventListener('change', sync);
    if (window.jQuery) window.jQuery(form).on('woocommerce_variation_has_changed reset_data', sync);
  }
})();
