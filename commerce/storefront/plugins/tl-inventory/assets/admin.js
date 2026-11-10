// TL Inventory: expand product rows, Adjust stock side panel with a live before → after preview.
(() => {
  document.querySelectorAll('[data-toggle]').forEach((b) => b.addEventListener('click', () => {
    const g = b.closest('[data-group]'); const open = !g.classList.contains('open');
    g.classList.toggle('open', open); b.setAttribute('aria-expanded', String(open));
  }));
  const panel = document.getElementById('tli-adjust');
  if (!panel) return;
  const f = (k) => panel.querySelector(`[data-f="${k}"]`);
  const qty = panel.querySelector('input[name=qty]');
  let before = null;
  const preview = () => {
    const r = panel.querySelector('input[name=reason]:checked'); const sign = Number(r.dataset.sign); const n = Math.abs(parseInt(qty.value || '0', 10));
    const b = before ?? 0; const after = sign === 0 ? n : b + sign * n;
    panel.querySelector('[data-qlabel]').textContent = sign === 0 ? 'Exact count after the stocktake' : sign > 0 ? 'Quantity to add' : 'Quantity to remove';
    f('before').textContent = before === null ? '0 (not counted yet)' : String(b); f('after').textContent = String(after);
    panel.querySelector('[data-preview]').classList.toggle('bad', after < 0);
  };
  document.querySelectorAll('[data-adjust]').forEach((btn) => btn.addEventListener('click', () => {
    f('id').value = btn.dataset.id; f('name').textContent = btn.dataset.name;
    before = btn.dataset.qty === '' ? null : Number(btn.dataset.qty); qty.value = ''; preview(); panel.showModal(); qty.focus();
  }));
  panel.addEventListener('input', preview); panel.addEventListener('change', preview);
  panel.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => panel.close()));
  panel.addEventListener('click', (e) => { if (e.target === panel) panel.close(); });
})();
