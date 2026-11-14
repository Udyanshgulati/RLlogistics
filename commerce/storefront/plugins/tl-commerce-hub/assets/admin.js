// TL Commerce Hub: expand product rows, Adjust stock side panel with a live before → after preview.
(() => {
  document.querySelectorAll('[data-toggle]').forEach((b) => b.addEventListener('click', () => {
    const g = b.closest('[data-group]'); const open = !g.classList.contains('open');
    g.classList.toggle('open', open); b.setAttribute('aria-expanded', String(open));
  }));
  // Orders: "select all" ticks every order in that table (for pick lists / packing slips).
  document.querySelectorAll('[data-check-all]').forEach((all) => all.addEventListener('change', () => {
    all.closest('table').querySelectorAll('tbody input[name="o[]"]').forEach((c) => { c.checked = all.checked; });
  }));
  // Purchase order form: add rows; show the product name for a typed SKU.
  const lines = document.querySelector('[data-po-lines]');
  if (lines) {
    const names = new Map([...document.querySelectorAll('#tli-skus option')].map((o) => [o.value, o.textContent]));
    const label = (input) => { const td = input.closest('tr').querySelector('[data-label]'); const v = input.value.trim(); td.textContent = v === '' ? '' : (names.get(v) || 'Unknown SKU'); td.classList.toggle('bad', v !== '' && !names.has(v)); };
    lines.addEventListener('input', (e) => { if (e.target.name === 'sku[]') label(e.target); });
    document.querySelector('[data-po-add]').addEventListener('click', () => {
      const row = lines.lastElementChild.cloneNode(true);
      row.querySelectorAll('input').forEach((i) => { i.value = ''; }); row.querySelector('[data-label]').textContent = '';
      lines.appendChild(row); row.querySelector('input').focus();
    });
  }
  // "All to pack" printouts follow the warehouse chosen in the print bar.
  document.querySelectorAll('[data-print-all]').forEach((a) => a.addEventListener('click', () => {
    const sel = document.querySelector('[data-print-wh]'); const u = new URL(a.href);
    if (sel && sel.value) u.searchParams.set('wh', sel.value); else u.searchParams.delete('wh');
    a.href = u.toString();
  }));
  const panel = document.getElementById('tli-adjust');
  if (!panel) return;
  const f = (k) => panel.querySelector(`[data-f="${k}"]`);
  const qty = panel.querySelector('input[name=qty]');
  let before = null; let whMap = null; let total = null;
  const whSel = panel.querySelector('select[name=wh]'); const toWrap = panel.querySelector('[data-to]');
  const preview = () => {
    const r = panel.querySelector('input[name=reason]:checked'); const sign = Number(r.dataset.sign); const n = Math.abs(parseInt(qty.value || '0', 10));
    // With warehouses, the preview is for the chosen warehouse.
    before = whMap && whSel ? (total === null ? null : Number(whMap[whSel.value] || 0)) : total;
    const b = before ?? 0; const after = sign === 0 ? n : b + sign * n;
    if (toWrap) {
      toWrap.hidden = r.value !== 'transfer';
      const to = toWrap.querySelector('select');
      if (r.value === 'transfer' && to.value === whSel.value) { const o = [...to.options].find((x) => x.value !== whSel.value); if (o) to.value = o.value; }
    }
    panel.querySelector('[data-qlabel]').textContent = r.value === 'transfer' ? 'Quantity to move' : sign === 0 ? 'Exact count after the stocktake' : sign > 0 ? 'Quantity to add' : 'Quantity to remove';
    f('before').textContent = before === null ? '0 (not counted yet)' : String(b); f('after').textContent = String(after);
    panel.querySelector('[data-preview]').classList.toggle('bad', after < 0);
  };
  document.querySelectorAll('[data-adjust]').forEach((btn) => btn.addEventListener('click', () => {
    f('id').value = btn.dataset.id; f('name').textContent = btn.dataset.name;
    total = btn.dataset.qty === '' ? null : Number(btn.dataset.qty);
    try { whMap = btn.dataset.wh ? JSON.parse(btn.dataset.wh) : null; } catch (e) { whMap = null; }
    qty.value = ''; preview(); panel.showModal(); qty.focus();
  }));
  panel.addEventListener('input', preview); panel.addEventListener('change', preview);
  panel.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => panel.close()));
  panel.addEventListener('click', (e) => { if (e.target === panel) panel.close(); });
})();
