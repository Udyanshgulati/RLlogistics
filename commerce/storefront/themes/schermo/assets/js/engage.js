// Schermo visitor journey: currency note → welcome → Schermo list (offer), one prompt at a time, paced by time and
// real interest, remembered in the browser. Never on cart/checkout/account (the server does not load this there).
(() => {
  'use strict';
  const cfg = window.schermoEngage || {};
  const $ = (s, r = document) => r.querySelector(s);
  const store = {
    get: (k) => { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: (k, v) => { try { localStorage.setItem(k, String(v)); } catch (e) { /* private mode */ } },
  };
  const now = () => Date.now();
  const busy = () => !!document.querySelector('dialog[open]') || !$('[data-eg-market]')?.hidden;
  const cookie = (n) => document.cookie.split('; ').some((c) => c.startsWith(`${n}=`));
  const setMarket = (id) => { document.cookie = `bh_market=${id}; path=/; max-age=31536000; samesite=lax${location.protocol === 'https:' ? '; secure' : ''}`; };

  // Session start (for pacing across pages of one visit).
  let start = 0;
  try { start = Number(sessionStorage.getItem('eg_start') || 0); if (!start) { start = now(); sessionStorage.setItem('eg_start', String(start)); } } catch (e) { start = now(); }

  // ---- Interest signals
  if (cfg.isProduct) store.set('eg_pv', Number(store.get('eg_pv') || 0) + 1);
  let deep = false;
  const onScroll = () => {
    const h = document.documentElement;
    if (!deep && (h.scrollTop + innerHeight) / h.scrollHeight > 0.6 && now() - start > 30000) { deep = true; store.set('eg_deep', 1); }
  };
  addEventListener('scroll', onScroll, { passive: true });
  document.addEventListener('click', (e) => { if (e.target.closest('.card-qa, .single_add_to_cart_button')) store.set('eg_qa', Number(store.get('eg_qa') || 0) + 1); });
  const interested = () => Number(store.get('eg_pv') || 0) >= 2 || Number(store.get('eg_qa') || 0) >= 1 || store.get('eg_deep') === '1';

  // ---- 1. Currency follows location (first visit only, ~6 s in)
  const market = $('#market-picker');
  const toast = $('[data-eg-market]');
  let marketDone = cookie('bh_market') || !market || !toast;
  const closeToast = () => { toast.hidden = true; toast.classList.remove('is-on'); marketDone = true; store.set('eg_market_at', now()); };
  const label = (id) => { const i = market.querySelector(`input[name=bh_market][value="${id}"]`); const l = i && i.closest('label'); return l ? l.textContent.replace(/\s+/g, ' ').trim() : id; };
  const current = () => market.querySelector('input[name=bh_market]:checked')?.value || '';
  if (!marketDone) {
    setTimeout(() => {
      fetch(cfg.detect, { credentials: 'same-origin' }).then((r) => r.json()).catch(() => ({})).then((d) => {
        const cur = current();
        const det = d && d.market ? d.market : cur;
        const text = $('[data-eg-market-text]', toast);
        const sw = $('[data-eg-market-switch]', toast);
        const keep = $('[data-eg-market-keep]', toast);
        if (det && det !== cur) {
          text.textContent = `Shopping from ${d.name || det}?`;
          sw.textContent = `Show prices in ${label(det).replace(/^.*?(\S+\s+\S+)$/, '$1')}`;
          sw.hidden = false;
          sw.onclick = () => { setMarket(det); location.reload(); };
          keep.textContent = 'Keep current prices';
          keep.onclick = () => { setMarket(cur); closeToast(); };
        } else {
          setMarket(cur || det);
          text.textContent = `Prices shown for ${label(cur || det)}.`;
          keep.textContent = 'Change';
          keep.onclick = () => { closeToast(); market.showModal(); };
        }
        $('[data-eg-market-close]', toast).onclick = () => { if (!cookie('bh_market') && cur) setMarket(cur); closeToast(); };
        toast.hidden = false;
        requestAnimationFrame(() => toast.classList.add('is-on'));
        setTimeout(() => { if (!toast.hidden && sw.hidden) closeToast(); }, 9000); // the confirmation fades by itself
      });
    }, 6000);
  }

  // ---- 2. Welcome (once per visitor, ~15 s in, after the currency note)
  const welcome = $('#eg-welcome');
  const tryWelcome = () => {
    if (!welcome || store.get('eg_welcome') || !marketDone || busy()) return false;
    if (now() - start < 15000 || now() - Number(store.get('eg_market_at') || 0) < 4000) return false;
    store.set('eg_welcome', now());
    welcome.showModal();
    return true;
  };

  // ---- 3. The Schermo list (after interest; not within 45 s of the welcome; 14 days after a dismissal)
  const list = $('#eg-list');
  const tryList = () => {
    if (!list || !cfg.newsletter || store.get('eg_list_done') || busy() || !interested()) return false;
    if (now() - Number(store.get('eg_list_dismissed') || 0) < 14 * 864e5) return false;
    const w = Number(store.get('eg_welcome') || 0);
    if (!w || now() - w < 45000) return false;
    list.showModal();
    list.addEventListener('close', () => { if (!store.get('eg_list_done')) store.set('eg_list_dismissed', now()); }, { once: true });
    return true;
  };

  const tick = setInterval(() => { if (tryWelcome() || tryList()) { /* one prompt per tick */ } if (store.get('eg_welcome') && (store.get('eg_list_done') || Number(store.get('eg_list_dismissed') || 0) > now() - 14 * 864e5) && marketDone) clearInterval(tick); }, 3000);

  // Sign-up form
  const form = list && $('[data-eg-form]', list);
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const err = $('[data-eg-error]', list);
      const email = form.email.value.trim();
      if (!form.email.checkValidity() || !email) { err.textContent = 'Please enter a valid email address.'; form.email.focus(); return; }
      if (!form.consent.checked) { err.textContent = 'Please tick the box to agree to receive our emails.'; form.consent.focus(); return; }
      err.textContent = '';
      const btn = form.querySelector('button[type=submit]');
      btn.classList.add('is-loading'); btn.disabled = true;
      fetch(cfg.newsletter, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email, consent: 1 }) })
        .then((r) => r.json().then((d) => ({ ok: r.ok, d })))
        .then(({ ok, d }) => {
          if (!ok || !d.ok) throw new Error(d && d.error ? d.error : 'Something went wrong. Please try again.');
          store.set('eg_list_done', now());
          form.hidden = true;
          const done = $('[data-eg-done]', list);
          if (d.code) { $('[data-eg-code]', done).textContent = d.code; } else { done.querySelector('.eg-code-label').textContent = 'You are on the list.'; done.querySelector('.eg-code').hidden = true; }
          done.hidden = false;
        })
        .catch((x) => { err.textContent = x.message; })
        .finally(() => { btn.classList.remove('is-loading'); btn.disabled = false; });
    });
    $('[data-eg-copy]', list).addEventListener('click', (e) => {
      const code = $('[data-eg-code]', list).textContent;
      (navigator.clipboard ? navigator.clipboard.writeText(code) : Promise.reject()).then(() => { e.target.textContent = 'Copied'; }).catch(() => {});
    });
  }
})();
