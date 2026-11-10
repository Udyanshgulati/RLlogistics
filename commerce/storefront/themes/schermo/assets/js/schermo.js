// Schermo homepage: hero product switcher (01–04) and the New arrivals rail arrows.
(() => {
  'use strict';
  const hero = document.querySelector('[data-hx]');
  const data = window.schermoHero || [];
  if (hero && data.length > 1) {
    const slides = Array.from(hero.querySelectorAll('.hx-slide'));
    const dots = Array.from(hero.querySelectorAll('[data-hx-go]'));
    const $h = (s) => hero.querySelector(s);
    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let cur = 0, timer = 0;
    const show = (n) => {
      cur = (n + slides.length) % slides.length;
      slides.forEach((s, i) => { s.classList.toggle('is-active', i === cur); s.setAttribute('aria-hidden', String(i !== cur)); });
      dots.forEach((b, i) => { if (i === cur) b.setAttribute('aria-current', 'true'); else b.removeAttribute('aria-current'); });
      const d = data[cur];
      const name = $h('[data-hx-name]'); name.textContent = d.name; name.href = d.url;
      $h('[data-hx-coll]').textContent = d.coll;
      $h('[data-hx-price]').textContent = d.price;
      const cta = $h('[data-hx-cta]'); cta.href = d.ctaUrl; cta.classList.toggle('is-preview', !d.ready);
      $h('[data-hx-cta-text]').textContent = d.cta;
      $h('[data-hx-index]').textContent = String(cur + 1).padStart(2, '0');
    };
    const stop = () => clearInterval(timer);
    const auto = () => { stop(); if (!still) timer = setInterval(() => show(cur + 1), 7000); };
    dots.forEach((b) => b.addEventListener('click', () => { show(Number(b.dataset.hxGo)); auto(); }));
    $h('[data-hx-prev]').addEventListener('click', () => { show(cur - 1); auto(); });
    $h('[data-hx-next]').addEventListener('click', () => { show(cur + 1); auto(); });
    // Swipe on the product image (phones and tablets).
    let x0 = null;
    const stage = $h('[data-hx-stage]');
    stage.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; stop(); }, { passive: true });
    stage.addEventListener('touchend', (e) => { if (x0 === null) return; const dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 40) show(cur + (dx < 0 ? 1 : -1)); x0 = null; auto(); });
    hero.addEventListener('pointerenter', stop);
    hero.addEventListener('pointerleave', auto);
    hero.addEventListener('focusin', stop);
    hero.addEventListener('focusout', auto);
    auto();
  }
  const rail = document.querySelector('[data-rail]');
  if (rail) {
    const by = (dir) => rail.scrollBy({ left: dir * rail.clientWidth * 0.8, behavior: 'smooth' });
    document.querySelector('[data-rail-prev]')?.addEventListener('click', () => by(-1));
    document.querySelector('[data-rail-next]')?.addEventListener('click', () => by(1));
  }
})();
