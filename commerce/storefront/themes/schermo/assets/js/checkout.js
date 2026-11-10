// Schermo checkout: a step indicator for the real sections of WooCommerce's one-page block checkout
// (Contact → Delivery → Payment), the "Your Schermo selection" heading on the order summary, and a hint for the
// optional phone field. WooCommerce renders the checkout with React, so everything here waits for it and only adds
// to it; validation, totals, shipping, tax, coupons and payment stay WooCommerce's and the payment provider's.
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const steps = {
    contact: () => $('#contact-fields') || $('.wc-block-checkout__contact-fields'),
    delivery: () => $('#shipping-fields') || $('#billing-fields') || $('.wc-block-checkout__shipping-fields'),
    payment: () => $('#payment-method') || $('.wc-block-checkout__payment-method'),
  };
  const items = () => Array.from(document.querySelectorAll('[data-ck-step]'));

  const complete = (el) => {
    if (!el) return false;
    if (el.querySelector('.has-error')) return false;
    const req = Array.from(el.querySelectorAll('input[required], select[required]')).filter((i) => i.offsetParent !== null);
    return req.length > 0 && req.every((i) => i.value.trim() !== '' && (!i.checkValidity || i.checkValidity()));
  };

  const refresh = () => {
    items().forEach((li) => {
      const key = li.dataset.ckStep;
      const el = steps[key]();
      const done = key !== 'payment' && complete(el);
      const err = !!(el && el.querySelector('.has-error'));
      li.classList.toggle('is-done', done);
      li.classList.toggle('has-error', err);
      const sr = li.querySelector('.ck-sr');
      if (sr) sr.textContent = err ? ' — needs attention' : (done ? ' — complete' : '');
    });
  };

  const setCurrent = (key) => items().forEach((li) => {
    if (li.dataset.ckStep === key) li.querySelector('a').setAttribute('aria-current', 'step');
    else li.querySelector('a').removeAttribute('aria-current');
  });

  // Click a step: go to that section and focus its first field (the section keeps its own heading for context).
  document.addEventListener('click', (e) => {
    const a = e.target.closest('[data-ck-step] a');
    if (!a) return;
    const el = steps[a.parentElement.dataset.ckStep]();
    if (!el) return;
    e.preventDefault();
    el.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    const f = el.querySelector('input:not([type=hidden]), select, textarea, button');
    if (f) setTimeout(() => f.focus({ preventScroll: true }), 350);
  });

  const enhance = () => {
    const form = $('.wc-block-checkout__form');
    if (!form) return false;
    // Current step = the section nearest the top of the screen.
    const io = new IntersectionObserver((entries) => {
      const vis = entries.filter((en) => en.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
      if (vis) setCurrent(vis.target.dataset.ckKey);
    }, { rootMargin: '-30% 0px -55% 0px' });
    Object.entries(steps).forEach(([key, get]) => { const el = get(); if (el) { el.dataset.ckKey = key; io.observe(el); } });
    setCurrent('contact');
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    form.addEventListener('focusout', () => setTimeout(refresh, 50));
    new MutationObserver(() => { refresh(); decorate(); }).observe(form.closest('.wc-block-checkout') || form, { subtree: true, childList: true, attributes: true, attributeFilter: ['class'] });
    refresh();
    decorate();
    return true;
  };

  // Brand heading on the summary and a reason for the optional phone number (re-applied if React re-renders).
  const decorate = () => {
    const side = $('.wc-block-checkout__sidebar');
    if (side && !$('.ck-selection', side)) {
      const box = document.createElement('div');
      box.className = 'ck-selection';
      const k = document.createElement('p'); k.className = 'ck-selection-kicker'; k.textContent = 'The art of dressing.';
      const h = document.createElement('h2'); h.className = 'ck-selection-title'; h.textContent = 'Your Schermo selection';
      box.append(k, h);
      side.prepend(box);
    }
    document.querySelectorAll('#shipping-phone, #billing-phone').forEach((input) => {
      if (input.required || input.dataset.ckHint) return;
      const id = `${input.id}-why`;
      const p = document.createElement('p');
      p.id = id; p.className = 'ck-hint';
      p.textContent = 'Optional. Used only by the courier to reach you about this delivery.';
      (input.closest('.wc-block-components-text-input') || input).after(p);
      input.setAttribute('aria-describedby', [input.getAttribute('aria-describedby'), id].filter(Boolean).join(' '));
      input.dataset.ckHint = '1';
    });
  };

  if (!enhance()) {
    const mo = new MutationObserver(() => { if (enhance()) mo.disconnect(); });
    mo.observe(document.body, { childList: true, subtree: true });
  }
})();
