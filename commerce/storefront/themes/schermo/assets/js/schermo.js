// Schermo homepage: hero product switcher (01–04) and the New arrivals rail arrows.
(() => {
  'use strict';
  const hero = document.querySelector('[data-hero]');
  const data = window.schermoHero || [];
  if (hero && data.length) {
    const slides = Array.from(hero.querySelectorAll('.gx-slide'));
    const buttons = Array.from(hero.querySelectorAll('[data-slide]'));
    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let cur = 0, timer = 0;
    const show = (n) => {
      cur = (n + slides.length) % slides.length;
      slides.forEach((s, i) => { s.classList.toggle('is-active', i === cur); s.setAttribute('aria-hidden', String(i !== cur)); });
      buttons.forEach((b, i) => b.setAttribute('aria-current', String(i === cur)));
      const d = data[cur];
      hero.querySelector('[data-hero-name]').textContent = d.name;
      hero.querySelector('[data-hero-price]').textContent = d.price;
      hero.querySelector('[data-hero-link]').href = d.url;
    };
    const auto = () => { if (!still) { clearInterval(timer); timer = setInterval(() => show(cur + 1), 6000); } };
    buttons.forEach((b) => b.addEventListener('click', () => { show(Number(b.dataset.slide)); auto(); }));
    hero.addEventListener('pointerenter', () => clearInterval(timer));
    hero.addEventListener('pointerleave', auto);
    auto();
  }
  const rail = document.querySelector('[data-rail]');
  if (rail) {
    const by = (dir) => rail.scrollBy({ left: dir * rail.clientWidth * 0.8, behavior: 'smooth' });
    document.querySelector('[data-rail-prev]')?.addEventListener('click', () => by(-1));
    document.querySelector('[data-rail-next]')?.addEventListener('click', () => by(1));
  }
})();
