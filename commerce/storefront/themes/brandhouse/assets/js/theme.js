// Brandhouse — small progressive enhancements. Every feature works without JavaScript.
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

  // Mobile menu drawer.
  const drawer = $('#drawer-menu');
  $$('[data-menu-open]').forEach((b) => b.addEventListener('click', () => { drawer.showModal(); b.setAttribute('aria-expanded', 'true'); }));
  if (drawer) drawer.addEventListener('close', () => $$('[data-menu-open]').forEach((b) => b.setAttribute('aria-expanded', 'false')));

  // Submenus (e.g. Collections): the toggle button opens/closes; outside click and Escape close them.
  const closeSubs = (except) => $$('.menu-item-has-children.is-open').forEach((li) => {
    if (li === except || li.closest('#drawer-menu')) return;
    li.classList.remove('is-open');
    const t = $('.sub-toggle', li); if (t) t.setAttribute('aria-expanded', 'false');
  });
  document.addEventListener('click', (e) => {
    const t = e.target.closest('.sub-toggle');
    if (t) {
      const li = t.closest('li'); const open = !li.classList.contains('is-open');
      closeSubs(li); li.classList.toggle('is-open', open); li.classList.toggle('is-closed', !open); t.setAttribute('aria-expanded', String(open));
      return;
    }
    if (!e.target.closest('.nav-primary .menu-item-has-children')) closeSubs();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    $$('.nav-primary .menu-item-has-children').forEach((li) => { if (li.contains(document.activeElement)) li.classList.add('is-closed'); });
    closeSubs();
  });
  // A dropdown closed on purpose stays closed while focus is still inside it; it can open again once focus leaves.
  document.addEventListener('focusout', (e) => {
    const li = e.target.closest && e.target.closest('.nav-primary .menu-item-has-children');
    if (li && !li.contains(e.relatedTarget)) li.classList.remove('is-closed');
  });
  // In the phone drawer, the section holding the current page starts open.
  $$('#drawer-menu .current-menu-ancestor, #drawer-menu .current-menu-parent').forEach((li) => {
    li.classList.add('is-open'); const t = $('.sub-toggle', li); if (t) t.setAttribute('aria-expanded', 'true');
  });

  // Add to bag / Buy now: immediate pressed + loading state, and no double submits while the request is on its way.
  document.addEventListener('submit', (e) => {
    const form = e.target.closest('form.cart');
    if (!form) return;
    if (form.dataset.busy === '1') { e.preventDefault(); return; }
    const btn = e.submitter || form.querySelector('.single_add_to_cart_button');
    if (!btn || btn.disabled || btn.classList.contains('disabled')) return;
    form.dataset.busy = '1';
    btn.classList.add('is-loading');
    btn.setAttribute('aria-busy', 'true');
    // If the page is restored from the back/forward cache, the button must not stay busy.
    window.addEventListener('pageshow', () => { form.dataset.busy = ''; btn.classList.remove('is-loading'); btn.removeAttribute('aria-busy'); }, { once: true });
  });

  // Quick add from product cards. Uses WooCommerce's own AJAX add-to-cart (the same cart the product page uses);
  // sized products must have a size chosen first. The bag count updates from WooCommerce's cart fragments.
  const qa = document.getElementById('quick-add');
  const toast = $('.bh-toast');
  const ajaxUrl = (window.wc_add_to_cart_params && window.wc_add_to_cart_params.wc_ajax_url) ? window.wc_add_to_cart_params.wc_ajax_url.replace('%%endpoint%%', 'add_to_cart') : '/?wc-ajax=add_to_cart';
  let toastTimer;
  const say = (msg) => { if (!toast) return; toast.textContent = msg; toast.hidden = false; toast.classList.add('is-on'); clearTimeout(toastTimer); toastTimer = setTimeout(() => { toast.classList.remove('is-on'); setTimeout(() => { toast.hidden = true; }, 250); }, 2600); };
  const addToBag = async (id, url) => {
    const body = new URLSearchParams({ product_id: String(id), quantity: '1' });
    const res = await fetch(ajaxUrl, { method: 'POST', body, credentials: 'same-origin' });
    const data = await res.json();
    if (!data || data.error) { const e = new Error('add failed'); e.url = (data && data.product_url) || url; throw e; }
    Object.entries(data.fragments || {}).forEach(([sel, html]) => $$(sel).forEach((el) => { el.outerHTML = html; }));
    if (window.jQuery) window.jQuery(document.body).trigger('added_to_cart', [data.fragments, data.cart_hash]);
    return data;
  };
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.card-qa');
    if (!btn) return;
    e.preventDefault();
    const d = JSON.parse(btn.dataset.qa);
    if (!d.sizes.length) {
      btn.classList.add('is-busy'); btn.disabled = true;
      try { await addToBag(d.id, d.url); say(`${d.name} added to bag`); btn.classList.add('is-done'); setTimeout(() => btn.classList.remove('is-done'), 1600); }
      catch (err) { location.href = err.url || d.url; }
      finally { btn.classList.remove('is-busy'); btn.disabled = false; }
      return;
    }
    if (!qa) { location.href = d.url; return; }
    $('.qa-img', qa).src = d.image; $('.qa-name', qa).textContent = d.name; const pr = $('.qa-price', qa); pr.replaceChildren(); if (d.was) { const s = document.createElement('s'); s.textContent = d.was; s.className = 'qa-was'; pr.append(s, ' '); } pr.append(d.price);
    $('.qa-more', qa).href = d.url; $('.qa-msg', qa).textContent = ''; $('.qa-bag', qa).hidden = true;
    const grid = $('.qa-grid', qa); grid.replaceChildren();
    d.sizes.forEach((s) => {
      const label = document.createElement('label'); label.className = 'qa-size';
      const input = document.createElement('input'); input.type = 'radio'; input.name = 'qa_size'; input.value = String(s.id); input.disabled = !s.ok;
      const span = document.createElement('span'); span.textContent = s.label;
      if (!s.ok) { const sr = document.createElement('span'); sr.className = 'sr-only'; sr.textContent = ' (sold out)'; span.append(sr); }
      label.append(input, span); grid.append(label);
    });
    const add = $('.qa-add', qa); add.disabled = true; add.classList.remove('is-loading');
    qa.dataset.url = d.url; qa.dataset.name = d.name;
    qa.showModal();
    const first = $('input:not(:disabled)', grid); if (first) first.focus();
  });
  if (qa) {
    qa.addEventListener('change', (e) => { if (e.target.name === 'qa_size') { $('.qa-add', qa).disabled = false; $('.qa-msg', qa).textContent = ''; } });
    $('.qa-add', qa).addEventListener('click', async () => {
      const picked = $('input[name=qa_size]:checked', qa);
      const add = $('.qa-add', qa);
      if (!picked) { $('.qa-msg', qa).textContent = 'Choose a size first.'; return; }
      add.classList.add('is-loading'); add.disabled = true;
      try {
        await addToBag(picked.value, qa.dataset.url);
        $('.qa-msg', qa).textContent = `Added to bag: ${qa.dataset.name}, size ${picked.nextElementSibling.firstChild.textContent}.`;
        $('.qa-bag', qa).hidden = false;
        setTimeout(() => { if (qa.open) qa.close(); }, 1400);
        say(`${qa.dataset.name} added to bag`);
      } catch (err) {
        $('.qa-msg', qa).textContent = 'Could not add this size. Opening the product page…';
        setTimeout(() => { location.href = err.url || qa.dataset.url; }, 900);
      } finally { add.classList.remove('is-loading'); add.disabled = false; }
    });
  }

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
  if (market && market.hasAttribute('data-autoopen') && !document.body.classList.contains('has-engage')) {
    market.showModal();
    const note = market.querySelector('[data-detect-url]');
    if (note && window.fetch) fetch(note.dataset.detectUrl, { credentials: 'same-origin' }).then((r) => r.json()).then((d) => {
      if (!d || !d.market) return;
      const radio = market.querySelector(`input[name=bh_market][value="${d.market}"]`);
      if (radio) radio.checked = true;
      if (d.name) { note.textContent = note.dataset.tpl.replace('%s', d.name); note.hidden = false; }
    }).catch(() => {});
    market.addEventListener('close', () => {
      if (document.cookie.includes('bh_market=')) return;
      const picked = market.querySelector('input[name=bh_market]:checked');
      if (picked) document.cookie = `bh_market=${picked.value}; path=/; max-age=31536000; samesite=lax${location.protocol === 'https:' ? '; secure' : ''}`;
    });
  }

  // Product photos: a thumbnail shows its photo in the large frame (quick fade); arrow keys move along the row.
  $$('[data-gallery]').forEach((g) => {
    const main = $('[data-gallery-main]', g);
    const thumbs = $$('[data-gallery-thumb]', g);
    if (!main || !thumbs.length) return;
    const show = (t) => {
      if (t.getAttribute('aria-current') === 'true') return;
      thumbs.forEach((x) => x.removeAttribute('aria-current'));
      t.setAttribute('aria-current', 'true');
      const next = new Image();
      next.srcset = t.dataset.srcset; next.src = t.dataset.src;
      const swap = () => { main.srcset = t.dataset.srcset; main.src = t.dataset.src; main.alt = t.dataset.alt; main.classList.remove('is-swapping'); };
      main.classList.add('is-swapping');
      // Swap once the photo is decoded, or after 250 ms at most, so a click always gets an answer.
      Promise.race([next.decode ? next.decode() : Promise.resolve(), new Promise((r) => setTimeout(r, 250))]).then(swap, swap);
    };
    thumbs.forEach((t, i) => {
      t.addEventListener('click', (e) => { e.preventDefault(); show(t); });
      t.addEventListener('mouseenter', () => show(t));
      t.addEventListener('keydown', (e) => {
        const d = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
        if (!d) return;
        e.preventDefault();
        const n = thumbs[(i + d + thumbs.length) % thumbs.length]; n.focus(); show(n);
      });
    });
  });

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

  // Size buttons on products not yet on sale: selectable, for a feel of the page.
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-preview-size]');
    if (!b) return;
    b.parentElement.querySelectorAll('[data-preview-size]').forEach((x) => x.setAttribute('aria-checked', String(x === b)));
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
