// Small progressive enhancements. Every form works without this file; the server validates everything again.
(() => {
  'use strict';
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  // Brand filter and other auto-submitting selects.
  $$('select[data-autosubmit]').forEach((s) => s.addEventListener('change', () => s.form.submit()));

  // Mobile navigation.
  const shell = $('.shell');
  $$('[data-nav-toggle]').forEach((b) => b.addEventListener('click', () => shell.classList.toggle('nav-open')));

  // "/" focuses search, like most admin tools.
  document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
      const s = $('#global-search');
      if (s) { e.preventDefault(); s.focus(); }
    }
  });

  $$('[data-print]').forEach((b) => b.addEventListener('click', () => window.print()));

  // Flash dismissal.
  $$('.flash .x').forEach((b) => b.addEventListener('click', () => b.closest('.flash').remove()));

  // Confirmation before high-impact actions: <form data-confirm="Message" data-confirm-title="Title">.
  const dialog = $('#confirm-dialog');
  let pending = null;
  $$('form[data-confirm]').forEach((f) => f.addEventListener('submit', (e) => {
    if (f.dataset.confirmed === '1' || !dialog) return;
    e.preventDefault();
    pending = f;
    $('[data-title]', dialog).textContent = f.dataset.confirmTitle || 'Are you sure?';
    $('[data-message]', dialog).textContent = f.dataset.confirm;
    $('[data-ok]', dialog).textContent = f.dataset.confirmOk || 'Confirm';
    dialog.showModal();
  }));
  if (dialog) {
    $('[data-ok]', dialog).addEventListener('click', () => { dialog.close(); if (pending) { pending.dataset.confirmed = '1'; pending.requestSubmit ? pending.requestSubmit() : pending.submit(); } });
    $('[data-cancel]', dialog).addEventListener('click', () => { dialog.close(); pending = null; });
  }

  // Live result preview on the Adjust stock form.
  const adjust = $('#adjust-form');
  if (adjust) {
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
    const render = () => {
      const type = ($('input[name=type]:checked', adjust) || {}).value;
      const q = parseInt(qty.value, 10);
      if (reasonLabel) reasonLabel.textContent = type && needsReason.includes(type) ? 'Reason (required)' : 'Reason (optional)';
      if (reason) reason.required = !!type && needsReason.includes(type);
      if (!type || Number.isNaN(q) || q === 0) { out.hidden = true; submit.disabled = !type; return; }
      const dl = deltas[type](q);
      const after = { ...level };
      Object.keys(dl).forEach((k) => { after[k] += dl[k]; });
      let problem = null;
      if (q < 0 && !negativeOk.includes(type)) problem = 'Enter a quantity greater than zero.';
      else if (after.onHand < 0) problem = `Only ${level.onHand} units are on hand.`;
      else if (avail(after) < 0) problem = `Only ${avail(level)} units are available; the rest are reserved for orders or held.`;
      out.hidden = false;
      $('[data-onhand]', out).innerHTML = `${level.onHand}<span class="arrow">→</span><span class="${after.onHand !== level.onHand ? 'changed' : ''}">${after.onHand}</span>`;
      $('[data-available]', out).innerHTML = `${avail(level)}<span class="arrow">→</span><span class="${avail(after) !== avail(level) ? 'changed' : ''}">${avail(after)}</span>`;
      const p = $('[data-problem]', out);
      p.textContent = problem || '';
      p.hidden = !problem;
      submit.disabled = !!problem;
    };
    adjust.addEventListener('input', render);
    adjust.addEventListener('change', render);
    render();
  }

  // Select-all checkboxes (pick list).
  $$('[data-check-all]').forEach((all) => all.addEventListener('change', () => {
    $$(`input[name="${all.dataset.checkAll}"]`).forEach((c) => { c.checked = all.checked; });
  }));
  const pick = $('#picklist-btn');
  if (pick) {
    pick.addEventListener('click', (e) => {
      const ids = $$('input[name="pick"]:checked').map((c) => c.value);
      if (ids.length) { e.preventDefault(); window.location = `/orders/picklist?ids=${ids.join(',')}`; }
    });
  }

  // Product form: show the right variation inputs.
  const variants = $$('input[name=variants]');
  if (variants.length) {
    const sync = () => {
      const v = ($('input[name=variants]:checked') || {}).value;
      $$('[data-variants]').forEach((el) => { el.hidden = el.dataset.variants !== v; });
    };
    variants.forEach((r) => r.addEventListener('change', sync));
    sync();
  }

  // Product code prefix hint follows the chosen brand.
  const brandSel = $('#brand_id');
  const code = $('#code');
  if (brandSel && code) {
    brandSel.addEventListener('change', () => {
      const prefix = brandSel.selectedOptions[0].dataset.prefix;
      if (prefix && (!code.value || /^[A-Z]{2,3}-/.test(code.value))) code.placeholder = `${prefix}-JER-001`;
    });
  }
})();
