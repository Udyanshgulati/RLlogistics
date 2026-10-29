// Progressive enhancements. Every action works without this file; the server re-validates everything.
(() => {
  'use strict';
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Auto-submitting selects (brand filter).
  $$('select[data-autosubmit]').forEach((s) => s.addEventListener('change', () => s.form.submit()));

  // Mobile navigation.
  const shell = $('.shell');
  $$('[data-nav-toggle]').forEach((b) => b.addEventListener('click', () => shell.classList.toggle('nav-open')));

  // "/" focuses search.
  document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName) && !document.querySelector('dialog[open]')) {
      const s = $('#global-search');
      if (s) { e.preventDefault(); s.focus(); }
    }
  });

  $$('[data-print]').forEach((b) => b.addEventListener('click', () => window.print()));
  $$('.flash .x').forEach((b) => b.addEventListener('click', () => b.closest('.flash').remove()));

  // Applying filters: dim the results while the next page loads.
  $$('[data-filter-card] .toolbar').forEach((f) => f.addEventListener('submit', () => f.closest('[data-filter-card]').classList.add('is-loading')));

  // Confirmation before high-impact actions: <form data-confirm="Message" data-confirm-title="Title">.
  const dialog = $('#confirm-dialog');
  let pending = null;
  document.addEventListener('submit', (e) => {
    const f = e.target;
    if (!(f instanceof HTMLFormElement) || !f.dataset.confirm || f.dataset.confirmed === '1' || !dialog) return;
    e.preventDefault();
    pending = f;
    $('[data-title]', dialog).textContent = f.dataset.confirmTitle || 'Are you sure?';
    $('[data-message]', dialog).textContent = f.dataset.confirm;
    $('[data-ok]', dialog).textContent = f.dataset.confirmOk || 'Confirm';
    dialog.showModal();
  });
  if (dialog) {
    $('[data-ok]', dialog).addEventListener('click', () => {
      dialog.close();
      if (pending) { pending.dataset.confirmed = '1'; pending.requestSubmit ? pending.requestSubmit() : pending.submit(); }
    });
    $('[data-cancel]', dialog).addEventListener('click', () => { dialog.close(); pending = null; });
  }

  // Submitting a form: lock its button so a double click cannot send it twice.
  document.addEventListener('submit', (e) => {
    const f = e.target;
    if (e.defaultPrevented || !(f instanceof HTMLFormElement) || f.method.toLowerCase() !== 'post') return;
    $$('button[type=submit], button:not([type])', f).forEach((b) => { b.disabled = true; b.classList.add('is-busy'); });
  });

  // ── Adjust stock: live result preview ────────────────────────────────
  function initAdjust(root) {
    const adjust = $('#adjust-form', root);
    if (!adjust) return;
    const d = adjust.dataset;
    const level = { onHand: +d.onHand, reserved: +d.reserved, damaged: +d.damaged, quarantine: +d.quarantine };
    const qty = $('#quantity', adjust);
    const reason = $('#reason', adjust);
    const reasonLabel = $('#reason-label', adjust);
    const submit = $('button[type=submit]', adjust);
    const out = $('#preview', adjust);
    const deltas = {
      received: (q) => ({ onHand: q }), returned_to_inventory: (q) => ({ onHand: q }), damaged: (q) => ({ damaged: q }),
      lost: (q) => ({ onHand: -q }), correction: (q) => ({ onHand: q }), other: (q) => ({ onHand: q }),
    };
    const needsReason = ['lost', 'correction', 'other'];
    const negativeOk = ['correction', 'other'];
    const avail = (l) => l.onHand - l.reserved - l.damaged - l.quarantine;
    const cell = (from, to) => `${from}<span class="arrow">→</span><span class="${to !== from ? 'changed' : ''}">${to}</span>`;
    const render = () => {
      const type = ($('input[name=type]:checked', adjust) || {}).value;
      const q = parseInt(qty.value, 10);
      if (reasonLabel) reasonLabel.textContent = type && needsReason.includes(type) ? 'Reason (required)' : 'Reason (optional)';
      if (reason) reason.required = !!type && needsReason.includes(type);
      if (!type || Number.isNaN(q) || q === 0) { out.hidden = true; submit.disabled = !type; return; }
      const after = { ...level };
      Object.entries(deltas[type](q)).forEach(([k, v]) => { after[k] += v; });
      let problem = null;
      if (q < 0 && !negativeOk.includes(type)) problem = 'Enter a quantity greater than zero.';
      else if (after.onHand < 0) problem = `Only ${level.onHand} units are on hand.`;
      else if (avail(after) < 0) problem = `Only ${avail(level)} units are available; the rest are reserved for orders or held.`;
      out.hidden = false;
      $('[data-onhand]', out).innerHTML = cell(level.onHand, after.onHand);
      $('[data-available]', out).innerHTML = cell(avail(level), avail(after));
      const p = $('[data-problem]', out);
      p.textContent = problem || '';
      p.hidden = !problem;
      submit.disabled = !!problem;
    };
    adjust.addEventListener('input', render);
    adjust.addEventListener('change', render);
    render();
  }
  initAdjust(document);

  // ── Side panel (drawer) ──────────────────────────────────────────────
  const drawer = $('#drawer');
  const drawerContent = drawer && $('[data-drawer-content]', drawer);
  const closeDrawer = () => {
    if (!drawer || !drawer.open) return;
    if (reduced) { drawer.close(); return; }
    drawer.classList.add('is-closing');
    setTimeout(() => { drawer.classList.remove('is-closing'); drawer.close(); }, 180);
  };
  document.addEventListener('click', async (e) => {
    const trigger = e.target.closest('[data-drawer]');
    if (trigger && drawer && !e.metaKey && !e.ctrlKey) {
      e.preventDefault();
      drawerContent.innerHTML = '<div class="drawer-loading" role="status">Loading…</div>';
      drawer.showModal();
      try {
        const res = await fetch(trigger.dataset.drawer, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
        if (!res.ok || res.redirected) throw new Error('load');
        drawerContent.innerHTML = await res.text();
        initAdjust(drawerContent);
        const first = $('input[name=type]', drawerContent);
        if (first) first.focus();
      } catch {
        window.location = trigger.getAttribute('href'); // fall back to the full page
      }
      return;
    }
    if (e.target.closest('[data-drawer-close]')) closeDrawer();
    if (e.target === drawer) closeDrawer(); // click on the backdrop
  });
  if (drawer) drawer.addEventListener('cancel', (e) => { e.preventDefault(); closeDrawer(); });

  // ── Inventory: expand sizes, bulk selection ─────────────────────────
  $$('[data-expand]').forEach((b) => b.addEventListener('click', () => {
    const group = b.closest('[data-group]');
    const open = !group.classList.contains('is-open');
    group.classList.toggle('is-open', open);
    b.setAttribute('aria-expanded', open ? 'true' : 'false');
  }));
  const bulkbar = $('[data-bulkbar]');
  const selected = () => $$('input[name="sku"]:checked').map((c) => c.value);
  const syncBulk = () => {
    if (!bulkbar) return;
    const ids = selected();
    bulkbar.hidden = ids.length === 0;
    $('[data-bulk-count]', bulkbar).textContent = ids.length;
    const form = $('[data-bulk-form]', bulkbar);
    if (form) {
      $$('input[name="ids[]"]', form).forEach((i) => i.remove());
      ids.forEach((id) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = id; form.appendChild(i); });
    }
    $$('[data-group]').forEach((g) => {
      const boxes = $$('input[name="sku"]', g);
      const gc = $('[data-group-check]', g);
      if (gc) { const n = boxes.filter((c) => c.checked).length; gc.checked = n === boxes.length; gc.indeterminate = n > 0 && n < boxes.length; }
    });
  };
  document.addEventListener('change', (e) => {
    const t = e.target;
    if (t.matches('[data-group-check]')) $$('input[name="sku"]', t.closest('[data-group]')).forEach((c) => { c.checked = t.checked; });
    if (t.matches('[data-check-all]')) $$(`input[name="${t.dataset.checkAll}"]`).forEach((c) => { c.checked = t.checked; });
    if (t.matches('input[name="sku"], [data-group-check], [data-check-all="sku"]')) syncBulk();
  });
  if (bulkbar) {
    const exp = $('[data-bulk-export]', bulkbar);
    if (exp) exp.addEventListener('click', (e) => { e.preventDefault(); window.location = `/inventory/export?ids=${selected().join(',')}`; });
    $('[data-bulk-clear]', bulkbar).addEventListener('click', () => { $$('input[name="sku"], [data-group-check], [data-check-all]').forEach((c) => { c.checked = false; c.indeterminate = false; }); syncBulk(); });
  }

  // Orders: print a pick list for the selected rows.
  const pick = $('#picklist-btn');
  if (pick) pick.addEventListener('click', (e) => {
    const ids = $$('input[name="pick"]:checked').map((c) => c.value);
    if (ids.length) { e.preventDefault(); window.location = `/orders/picklist?ids=${ids.join(',')}`; }
  });

  // ── Order journey: animate only a stage the server has confirmed since this browser last saw the order ──
  $$('[data-journey]').forEach((j) => {
    const key = `journey:${j.dataset.journey}`;
    const now = +j.dataset.stage;
    let before = null;
    try { before = sessionStorage.getItem(key); sessionStorage.setItem(key, String(now)); } catch { /* storage blocked: no animation */ }
    if (reduced || before === null || +before >= now) return;
    $$('li', j).forEach((li, i) => { if (i > +before && i <= now) li.classList.add('just-done'); });
    j.classList.add('is-advancing');
  });

  // Product form: show the right variation inputs; code placeholder follows the brand.
  const variants = $$('input[name=variants]');
  if (variants.length) {
    const sync = () => {
      const v = ($('input[name=variants]:checked') || {}).value;
      $$('[data-variants]').forEach((el) => { el.hidden = el.dataset.variants !== v; });
    };
    variants.forEach((r) => r.addEventListener('change', sync));
    sync();
  }
  const brandSel = $('#brand_id');
  const code = $('#code');
  if (brandSel && code) brandSel.addEventListener('change', () => {
    const prefix = brandSel.selectedOptions[0].dataset.prefix;
    if (prefix) code.placeholder = `${prefix}-JER-001`;
  });
})();
