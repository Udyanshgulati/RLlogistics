<?php
/**
 * Schermo — Coming soon. Standalone page (no shop header/footer). See inc/coming-soon.php.
 *
 * A bright studio, editorial poster typography in the corners, "COMING SOON" above one long steel rail, like a
 * boutique: every Schermo garment on its own black hanger (trousers and shorts on clip hangers), facing out, at the
 * same scale and evenly spaced, in a new shuffled order on each visit. Hover (or keyboard focus) lifts a piece and
 * names it; click opens it large with its collection and previous/next. The rail drifts slowly and can be dragged,
 * swiped or scrolled. A ticker runs along the bottom. Reduced motion: no drift, no sway.
 */

defined('ABSPATH') || exit;

$rel = static fn (string $u): string => wp_make_link_relative($u);
$dir = get_stylesheet_directory_uri();
$pieces = function_exists('schermo_soon_pieces') ? schermo_soon_pieces() : [];
$mark = function_exists('schermo_svg') ? schermo_svg('schermo-mark', 'cs-mark') : '';
$word = function_exists('schermo_svg') ? schermo_svg('schermo-wordmark', 'cs-word') : '';
$insta = function_exists('tli_invoice_conf') ? (string) (tli_invoice_conf()['instagram'] ?? '') : '';
$host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
$preview = !in_array($host, SCHERMO_SOON_HOSTS, true);
$ticker = ['Coming soon', 'The art of dressing', 'Schermo Core', 'Schermo Atelier', 'Schermo Sports', 'Milano · Est. 2021'];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Schermo — Coming soon</title>
<meta name="description" content="Schermo — The art of dressing. Milano, est. 2021. Coming soon.">
<?php if ($preview): ?><meta name="robots" content="noindex"><?php endif; ?>
<meta property="og:title" content="Schermo — Coming soon">
<meta property="og:image" content="<?php echo esc_url($dir . '/assets/img/share.jpg'); ?>">
<meta name="theme-color" content="#ecebe7">
<link rel="icon" type="image/svg+xml" href="<?php echo esc_url($rel($dir . '/assets/img/favicon.svg')); ?>">
<link rel="preload" href="<?php echo esc_url($rel($dir . '/assets/fonts/syncopate-700-latin.woff2')); ?>" as="font" type="font/woff2" crossorigin>
<style>
@font-face { font-family: "Syncopate"; font-weight: 700; font-display: swap; src: url("<?php echo esc_url($rel($dir . '/assets/fonts/syncopate-700-latin.woff2')); ?>") format("woff2"); }
@font-face { font-family: "Syncopate"; font-weight: 400; font-display: swap; src: url("<?php echo esc_url($rel($dir . '/assets/fonts/syncopate-400-latin.woff2')); ?>") format("woff2"); }
@font-face { font-family: "Inter"; font-weight: 100 900; font-display: swap; src: url("<?php echo esc_url($rel($dir . '/assets/fonts/inter-var-latin.woff2')); ?>") format("woff2"); }
:root { --ink: #111112; --ink-2: #3b3b3d; --muted: #77767a; --paper: #ecebe7; --paper-2: #f6f5f2; --ox: #8e0f1d; --gold: #b08a3a; --line: rgba(17, 17, 18, .12);
  --tw: clamp(118px, min(12vw, 19vh), 200px); --hang: #1c1a19; }
* { box-sizing: border-box; }
html, body { margin: 0; height: 100%; }
body { background: radial-gradient(70% 60% at 50% 42%, var(--paper-2), var(--paper) 70%, #e2e0db 100%); color: var(--ink); font: 400 14px/1.55 Inter, system-ui, sans-serif; -webkit-font-smoothing: antialiased; overflow: hidden; }
.label { font: 700 11px/1.25 Syncopate, sans-serif; letter-spacing: .08em; text-transform: uppercase; }
.page { position: fixed; inset: 0; display: grid; grid-template-rows: auto auto 1fr auto auto; }

/* Corners */
.top, .bottom { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; padding: clamp(18px, 3vw, 40px) clamp(18px, 4vw, 56px) 0; }
.cs-word { width: clamp(110px, 11vw, 150px); height: auto; fill: currentColor; color: var(--ink); display: block; }
.top .right { text-align: right; }

/* Title block */
.head { text-align: center; padding: clamp(6px, 2vh, 26px) 18px 0; }
.spark { display: block; margin: 0 auto 10px; width: 30px; height: 12px; color: var(--ox); }
h1 { margin: 0; font: 700 clamp(34px, 7.2vw, 104px)/.95 Syncopate, sans-serif; letter-spacing: -.01em; text-transform: uppercase; }
.lede { margin: clamp(10px, 1.6vh, 18px) auto 0; max-width: 46ch; font-size: clamp(13px, 1.15vw, 16px); color: var(--ink-2); }

/* Rail: R is the rod's centre line; every hanger hooks over it. */
.stage { position: relative; min-height: 0; display: flex; flex-direction: column; justify-content: center; }
.rail { position: relative; overflow: hidden; cursor: grab; touch-action: pan-y; -webkit-mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent); mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent); }
.rail.dragging { cursor: grabbing; }
.rod { position: absolute; left: 0; right: 0; top: 23px; height: 7px; border-radius: 4px; z-index: 1;
  background: linear-gradient(180deg, #fdfdfc 0%, #d4d3cf 30%, #9a9995 65%, #62615e 100%); box-shadow: 0 4px 8px rgba(0, 0, 0, .16); }
.track { position: absolute; top: 0; left: 0; height: 100%; display: flex; align-items: flex-start; will-change: transform; z-index: 2; }
.slot { position: relative; flex: none; height: 100%; outline: none; }
.hang { position: absolute; left: 50%; top: 0; transform-origin: 50% 26px; transition: transform .7s cubic-bezier(.2, .8, .2, 1); }
.hanger { position: absolute; top: 0; overflow: visible; }
.hanger path, .hanger rect { fill: none; stroke: var(--hang); stroke-width: 2.6; stroke-linecap: round; stroke-linejoin: round; }
.hanger rect { fill: var(--hang); stroke: none; }
.g { position: absolute; overflow: hidden; filter: drop-shadow(0 16px 14px rgba(40, 30, 25, .16)) drop-shadow(0 2px 3px rgba(40, 30, 25, .12)); transition: filter .5s; }
.g img { position: absolute; max-width: none; pointer-events: none; user-select: none; -webkit-user-drag: none; }
.slot.on .hang { transform: translateY(-5px); }
.slot.on .g { filter: drop-shadow(0 26px 22px rgba(40, 30, 25, .22)) drop-shadow(0 2px 3px rgba(40, 30, 25, .12)); }
.rail.hovering .slot:not(.on) .g { opacity: .72; }
.g { transition: filter .5s, opacity .5s; }
.shadow { position: absolute; left: 4%; right: 4%; bottom: 0; height: 30px; border-radius: 50%; background: radial-gradient(closest-side, rgba(0, 0, 0, .14), transparent); filter: blur(12px); pointer-events: none; }
.caption { text-align: center; min-height: 20px; margin-top: 6px; font-size: 12px; color: var(--muted); letter-spacing: .02em; }
.caption b { color: var(--ink); font-weight: 600; }
.probe { position: absolute; visibility: hidden; width: var(--tw); height: 0; }

/* Bottom */
.bottom { align-items: flex-end; padding-bottom: clamp(12px, 2vh, 22px); }
.notify { display: grid; gap: 8px; justify-items: end; }
.notify .row { display: flex; gap: 0; border-bottom: 1.5px solid var(--ink); min-width: min(360px, 60vw); }
.notify input[type=email] { flex: 1; min-width: 0; border: 0; background: transparent; font: inherit; padding: 8px 2px; color: var(--ink); outline: none; }
.notify button { border: 0; background: transparent; cursor: pointer; font: 700 11px/1 Syncopate, sans-serif; letter-spacing: .08em; text-transform: uppercase; color: var(--ox); padding: 8px 0 8px 14px; white-space: nowrap; }
.notify button:hover { color: var(--ink); }
.consent { display: flex; gap: 8px; align-items: center; font-size: 11px; color: var(--muted); cursor: pointer; }
.consent input { accent-color: var(--ox); margin: 0; }
.msg { min-height: 16px; font-size: 12px; color: var(--ox); margin: 0; text-align: right; max-width: 380px; }
.ticker { overflow: hidden; background: var(--ox); color: #f6efe4; border-top: 1px solid rgba(0, 0, 0, .15); }
.ticker div { display: flex; width: max-content; animation: tick 40s linear infinite; }
.ticker span { padding: 9px 22px; font: 700 10px/1 Syncopate, sans-serif; letter-spacing: .14em; text-transform: uppercase; white-space: nowrap; }
.ticker span::after { content: "✦"; margin-left: 44px; color: #e2c27a; }
@keyframes tick { to { transform: translateX(-50%); } }

/* Focus view */
.focus { position: fixed; inset: 0; z-index: 30; display: grid; grid-template-rows: auto 1fr auto; place-items: center; background: rgba(236, 235, 231, .86); -webkit-backdrop-filter: blur(14px); backdrop-filter: blur(14px); opacity: 0; pointer-events: none; transition: opacity .45s; }
.focus.open { opacity: 1; pointer-events: auto; }
.focus .close { justify-self: end; margin: clamp(16px, 3vw, 36px) clamp(16px, 4vw, 56px) 0; background: none; border: 0; cursor: pointer; font: 700 11px/1 Syncopate, sans-serif; letter-spacing: .08em; text-transform: uppercase; color: var(--ink); padding: 10px; }
.focus figure { margin: 0; display: grid; grid-template-columns: 48px minmax(0, auto) 48px; align-items: center; gap: clamp(12px, 4vw, 60px); }
.focus img { max-height: min(56vh, 560px); max-width: min(70vw, 460px); width: auto; filter: drop-shadow(0 30px 30px rgba(0, 0, 0, .2)); transform: translateY(10px); transition: transform .6s cubic-bezier(.2, .8, .2, 1); }
.focus.open img { transform: none; }
.arrow { width: 44px; height: 44px; border-radius: 50%; border: 1px solid var(--line); background: rgba(255, 255, 255, .6); cursor: pointer; font-size: 18px; color: var(--ink); }
.arrow:hover { background: #fff; }
.focus .meta { text-align: center; padding-bottom: clamp(20px, 5vh, 48px); }
.focus .count { font-size: 11px; color: var(--muted); letter-spacing: .1em; }
.focus h2 { margin: 6px 0 4px; font: 400 clamp(20px, 2.4vw, 30px)/1.2 Inter, sans-serif; letter-spacing: -.01em; }
.focus .coll { font: 700 10px/1 Syncopate, sans-serif; letter-spacing: .14em; text-transform: uppercase; color: var(--ox); }
.focus .soon { margin-top: 12px; display: inline-block; padding: 8px 16px; border-radius: 999px; border: 1px solid var(--ink); font: 700 10px/1 Syncopate, sans-serif; letter-spacing: .1em; text-transform: uppercase; }
.sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
:focus-visible { outline: 2px solid var(--ox); outline-offset: 3px; }

@media (max-width: 760px) {
  :root { --tw: clamp(104px, 30vw, 132px); }
  .top { padding-top: 16px; } .top .right { font-size: 9px; }
  h1 { font-size: clamp(30px, 10.5vw, 46px); }
  .bottom { flex-direction: column; align-items: stretch; gap: 10px; }
  .bottom .left { display: none; }
  .notify { justify-items: stretch; } .notify .row { min-width: 0; } .msg { text-align: left; }
  .focus figure { grid-template-columns: 40px minmax(0, auto) 40px; gap: 8px; }
  .focus img { max-width: 62vw; }
}
@media (prefers-reduced-motion: reduce) { *, *::before, *::after { transition: none !important; animation: none !important; } }
</style>
</head>
<body>
<div class="page">
  <header class="top">
    <a href="/" aria-label="Schermo"><?php echo $word; // phpcs:ignore WordPress.Security.EscapeOutput -- the brand's own SVG ?></a>
    <p class="label right" style="margin:0">Milano<br>Est. 2021</p>
  </header>

  <section class="head" aria-labelledby="cs-title">
    <svg class="spark" viewBox="0 0 30 12" aria-hidden="true"><path fill="currentColor" d="M15 0l1.6 4.4L21 6l-4.4 1.6L15 12l-1.6-4.4L9 6l4.4-1.6zM5 3l.8 2.2L8 6l-2.2.8L5 9l-.8-2.2L2 6l2.2-.8zM25 3l.8 2.2L28 6l-2.2.8L25 9l-.8-2.2L22 6l2.2-.8z"/></svg>
    <h1 id="cs-title">Coming soon</h1>
    <p class="lede">The art of dressing. Three collections — Schermo Core, Schermo Atelier and Schermo Sports — are being hung.</p>
  </section>

  <section class="stage" aria-label="The collection">
    <div class="rail" data-rail>
      <div class="rod"></div>
      <div class="track" role="list"></div>
      <div class="shadow"></div>
      <span class="probe" data-probe></span>
    </div>
    <p class="caption" aria-live="polite" data-caption>Hover a piece · drag the rail</p>
  </section>

  <footer class="bottom">
    <p class="label left" style="margin:0">The art<br>of dressing</p>
    <form class="notify" data-notify novalidate>
      <div class="row">
        <label class="sr" for="cs-email">Email address</label>
        <input id="cs-email" type="email" name="email" autocomplete="email" required placeholder="Your email — be first in">
        <button type="submit">Notify me</button>
      </div>
      <label class="consent"><input type="checkbox" name="consent" value="1" required> Email me when Schermo opens and about new collections. Unsubscribe any time.</label>
      <p class="msg" role="status" aria-live="polite"></p>
    </form>
  </footer>

  <div class="ticker" aria-hidden="true"><div><?php for ($k = 0; $k < 4; $k++) { foreach ($ticker as $t) { echo '<span>' . esc_html($t) . '</span>'; } } ?></div></div>
</div>

<div class="focus" data-focus role="dialog" aria-modal="true" aria-label="Piece">
  <button type="button" class="close" data-close>Close ✕</button>
  <figure>
    <button type="button" class="arrow" data-prev aria-label="Previous piece">←</button>
    <img alt="" data-fimg>
    <button type="button" class="arrow" data-next aria-label="Next piece">→</button>
  </figure>
  <div class="meta">
    <p class="count" data-fcount></p>
    <p class="coll" data-fcoll></p>
    <h2 data-fname></h2>
    <span class="soon">Coming soon</span>
  </div>
</div>

<script>
(() => {
  'use strict';
  const all = <?php echo wp_json_encode(array_values($pieces)); ?>;
  // Shuffled, but never two trousers/shorts side by side when it can be helped.
  const list = all.slice();
  for (let i = list.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [list[i], list[j]] = [list[j], list[i]]; }
  for (let i = 1; i < list.length; i++) {
    if (list[i].k === 'bottom' && list[i - 1].k === 'bottom') { const j = list.findIndex((p, k) => k > i && p.k === 'top'); if (j > 0) [list[i], list[j]] = [list[j], list[i]]; }
  }
  const still = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const rail = document.querySelector('[data-rail]'), track = rail.querySelector('.track'), cap = document.querySelector('[data-caption]');

  // Hang every piece: tops on a hanger (only hook and neck show above the collar), trousers and shorts on a clip
  // hanger. Sizes come from the garment's own box in its photo, so all pieces share one scale and one hanging line.
  const R = 26, NECK = 34, CLIP = 44, esc = (t) => t.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);
  const probe = document.querySelector('[data-probe]');
  let tw = 0;
  const build = () => {
    const w = probe.getBoundingClientRect().width;
    if (Math.abs(w - tw) < 1 && track.children.length) return;
    tw = w; track.textContent = '';
    const gap = Math.round(tw * .2), tallest = tw * 1.45;
    rail.style.height = Math.round(R + CLIP + tallest + 36) + 'px';
    [...list, ...list].forEach((p, k) => { // twice over, so the rail loops without a seam
      const [x0, y0, x1, y1] = p.b, bw = x1 - x0, bh = y1 - y0, a = (bh * p.r) / bw, bottom = p.k === 'bottom';
      // Tops share one width; trousers one length; shorts one waist width.
      let gw = tw, gh;
      if (bottom && !/short/i.test(p.n)) { gh = tallest; gw = Math.min(gh / a, tw * .85); gh = gw * a; }
      else { gw = bottom ? tw * .62 : tw; gh = gw * a; if (gh > tw * 1.4) { gh = tw * 1.4; gw = gh / a; } }
      const iw = gw / bw, ih = iw * p.r, sw = Math.max(48, gw + 26), cx = sw / 2, top = bottom ? R + CLIP : R + NECK;
      let hanger;
      if (bottom) {
        const bar = R + 30, half = gw / 2 + 6, cl = gw / 2 - Math.min(14, gw * .12);
        hanger = `<path d="M${cx} ${bar} L${cx} ${R + 2} A7 7 0 1 1 ${cx + 14} ${R + 2}"/><path d="M${cx - half} ${bar} L${cx + half} ${bar}"/>`
          + `<rect x="${cx - cl - 4}" y="${bar - 2}" width="8" height="${top - bar + 8}" rx="1.5"/><rect x="${cx + cl - 4}" y="${bar - 2}" width="8" height="${top - bar + 8}" rx="1.5"/>`;
      } else {
        hanger = `<path d="M${cx} ${top + 6} L${cx} ${R + 2} A7 7 0 1 1 ${cx + 14} ${R + 2}"/>`;
      }
      const s = document.createElement('div');
      s.className = 'slot'; s.setAttribute('role', 'listitem'); s.tabIndex = k < list.length ? 0 : -1; s.dataset.i = k % list.length;
      s.setAttribute('aria-label', p.n + (p.c ? ', ' + p.c : ''));
      s.style.width = Math.round(gw + gap) + 'px';
      s.innerHTML = `<div class="hang"><svg class="hanger" aria-hidden="true" width="${sw}" height="${top + 10}" style="left:${-cx}px">${hanger}</svg>`
        + `<div class="g" style="left:${-gw / 2}px;top:${top}px;width:${gw}px;height:${gh}px"><img src="${esc(p.u)}" alt="" decoding="async" loading="${k < 24 ? 'eager' : 'lazy'}" style="width:${iw}px;height:${ih}px;left:${-x0 * iw}px;top:${-y0 * ih}px"></div></div>`;
      if (bottom) s.querySelector('.g').after(s.querySelector('.hanger')); // clips sit over the waistband
      track.appendChild(s);
    });
    measure();
  };

  // Drift + drag (pointer), wheel, keyboard
  let x = 0, v = still ? 0 : -0.25, dragging = false, startX = 0, startOff = 0, moved = 0, half = 0, paused = false;
  function measure() { half = track.scrollWidth / 2; }
  build(); let rt; addEventListener('resize', () => { clearTimeout(rt); rt = setTimeout(() => { build(); measure(); }, 150); });
  const wrap = () => { if (half) { while (x <= -half) x += half; while (x > 0) x -= half; } };
  const frame = () => { if (!dragging && !paused) x += v; wrap(); track.style.transform = `translate3d(${x}px,0,0)`; requestAnimationFrame(frame); };
  requestAnimationFrame(frame);
  let downSlot = null;
  rail.addEventListener('pointerdown', (e) => { downSlot = e.target.closest('.slot'); dragging = true; moved = 0; startX = e.clientX; startOff = x; rail.classList.add('dragging'); rail.setPointerCapture(e.pointerId); });
  rail.addEventListener('pointermove', (e) => { if (!dragging) return; moved = Math.max(moved, Math.abs(e.clientX - startX)); x = startOff + (e.clientX - startX); });
  const end = () => { dragging = false; rail.classList.remove('dragging'); };
  // A press that did not move the rail is a click on the piece under it (pointer capture hides the slot from 'click').
  rail.addEventListener('pointerup', () => { const s = downSlot; end(); downSlot = null; if (s && moved < 6) open(+s.dataset.i); });
  rail.addEventListener('pointercancel', () => { end(); downSlot = null; });
  rail.addEventListener('wheel', (e) => { if (Math.abs(e.deltaX) > Math.abs(e.deltaY) || e.shiftKey) { x -= (e.deltaX || e.deltaY); e.preventDefault(); } }, { passive: false });

  // Turn to face: hover / focus
  let on = null;
  const show = (s) => {
    if (on === s) return;
    if (on) on.classList.remove('on');
    on = s;
    rail.classList.toggle('hovering', !!s);
    if (s) { s.classList.add('on'); const p = list[+s.dataset.i]; cap.innerHTML = `<b>${esc(p.n)}</b>${p.c ? ' — ' + esc(p.c) : ''}`; }
    else cap.textContent = 'Hover a piece · drag the rail';
  };
  track.addEventListener('pointerover', (e) => { const s = e.target.closest('.slot'); if (s && !dragging) { show(s); paused = true; } });
  rail.addEventListener('pointerleave', () => { show(null); paused = false; });
  track.addEventListener('focusin', (e) => { const s = e.target.closest('.slot'); if (s) { show(s); paused = true; } });
  track.addEventListener('focusout', () => { paused = false; });

  // Open large
  const fx = document.querySelector('[data-focus]'), fimg = fx.querySelector('[data-fimg]');
  let cur = 0, last = null;
  const render = () => { const p = list[cur]; fimg.src = p.u; fimg.alt = p.n; fx.querySelector('[data-fname]').textContent = p.n; fx.querySelector('[data-fcoll]').textContent = p.c;
    fx.querySelector('[data-fcount]').textContent = String(cur + 1).padStart(2, '0') + ' / ' + String(list.length).padStart(2, '0'); };
  const open = (i) => { cur = i; render(); last = document.activeElement; fx.classList.add('open'); paused = true; fx.querySelector('[data-close]').focus(); };
  const close = () => { fx.classList.remove('open'); paused = false; if (last) last.focus(); };
  track.addEventListener('keydown', (e) => { const s = e.target.closest('.slot'); if (s && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); open(+s.dataset.i); } });
  fx.querySelector('[data-close]').addEventListener('click', close);
  fx.querySelector('[data-prev]').addEventListener('click', () => { cur = (cur - 1 + list.length) % list.length; render(); });
  fx.querySelector('[data-next]').addEventListener('click', () => { cur = (cur + 1) % list.length; render(); });
  fx.addEventListener('click', (e) => { if (e.target === fx) close(); });
  addEventListener('keydown', (e) => { if (!fx.classList.contains('open')) return;
    if (e.key === 'Escape') close(); if (e.key === 'ArrowRight') fx.querySelector('[data-next]').click(); if (e.key === 'ArrowLeft') fx.querySelector('[data-prev]').click(); });

  // Notify me → the Schermo list (the site records consent).
  const form = document.querySelector('[data-notify]'), msg = form.querySelector('.msg'), btn = form.querySelector('button');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = form.email.value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { msg.textContent = 'Please enter a valid email address.'; form.email.focus(); return; }
    if (!form.consent.checked) { msg.textContent = 'Please tick the box so we may email you.'; return; }
    btn.disabled = true; msg.textContent = '…';
    try {
      const r = await fetch('/wp-json/scm/v1/newsletter', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email, consent: 1 }) });
      const d = await r.json().catch(() => ({}));
      if (!r.ok || !d.ok) throw new Error(d.error || 'Something went wrong. Please try again.');
      form.querySelector('.row').style.display = 'none'; form.querySelector('.consent').style.display = 'none';
      msg.textContent = 'You are on the list — we will email you the moment the doors open.' + (d.code ? ` Your welcome code: ${d.code} (${d.percent}% off your first order).` : '');
    } catch (err) { msg.textContent = err.message; btn.disabled = false; }
  });
})();
</script>
</body>
</html>
