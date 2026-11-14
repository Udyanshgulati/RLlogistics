<?php
/**
 * Schermo — Coming soon. Standalone page (no shop header/footer). See inc/coming-soon.php.
 * Scene: a private dressing room at night. Two brass rails carry every Schermo piece (shuffled on each visit), the
 * back rail softer and further away; warm spotlights from above, oxblood velvet behind, a polished floor that
 * reflects the front rail, slow haze and drifting gold dust. Everything moves slowly and stops for reduced motion.
 */

defined('ABSPATH') || exit;

$rel = static fn (string $u): string => wp_make_link_relative($u);
$dir = get_stylesheet_directory_uri();
$pieces = function_exists('schermo_soon_pieces') ? schermo_soon_pieces() : [];
$mark = function_exists('schermo_svg') ? schermo_svg('schermo-mark', 'cs-mark') : '';
$word = function_exists('schermo_svg') ? schermo_svg('schermo-wordmark', 'cs-word') : '';
$insta = function_exists('tli_invoice_conf') ? (string) (tli_invoice_conf()['instagram'] ?? '') : '';
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$preview = !in_array(preg_replace('/:\d+$/', '', $host), SCHERMO_SOON_HOSTS, true);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?php echo esc_html__('Schermo — Coming soon', 'schermo'); ?></title>
<meta name="description" content="<?php esc_attr_e('Schermo — The art of dressing. Milano, est. 2021. Coming soon.', 'schermo'); ?>">
<?php if ($preview): ?><meta name="robots" content="noindex"><?php endif; ?>
<meta property="og:title" content="Schermo — Coming soon">
<meta property="og:image" content="<?php echo esc_url($dir . '/assets/img/share.jpg'); ?>">
<meta name="theme-color" content="#070708">
<link rel="icon" type="image/svg+xml" href="<?php echo esc_url($rel($dir . '/assets/img/favicon.svg')); ?>">
<link rel="preload" href="<?php echo esc_url($rel($dir . '/assets/fonts/syncopate-700-latin.woff2')); ?>" as="font" type="font/woff2" crossorigin>
<style>
@font-face { font-family: "Syncopate"; font-weight: 700; font-display: swap; src: url("<?php echo esc_url($rel($dir . '/assets/fonts/syncopate-700-latin.woff2')); ?>") format("woff2"); }
@font-face { font-family: "Syncopate"; font-weight: 400; font-display: swap; src: url("<?php echo esc_url($rel($dir . '/assets/fonts/syncopate-400-latin.woff2')); ?>") format("woff2"); }
@font-face { font-family: "Inter"; font-weight: 100 900; font-display: swap; src: url("<?php echo esc_url($rel($dir . '/assets/fonts/inter-var-latin.woff2')); ?>") format("woff2"); }
:root { --ink: #f2f0eb; --muted: rgba(242, 240, 235, .62); --gold: #c9a24a; --gold-2: #f0d48a; --ox: #8e0f1d; --ox-deep: #3d050c; --bg: #070708; }
* { box-sizing: border-box; }
html, body { margin: 0; height: 100%; background: var(--bg); color: var(--ink); font: 400 15px/1.55 Inter, system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
body { overflow: hidden; }
.cs { position: fixed; inset: 0; overflow: hidden; isolation: isolate; perspective: 1400px; }

/* Room: velvet pleats with an oxblood glow, deep vignette */
.cs-wall { position: absolute; inset: -4%; z-index: 0;
  background:
    radial-gradient(60% 55% at 50% 38%, rgba(142, 15, 29, .55), transparent 70%),
    radial-gradient(35% 40% at 18% 30%, rgba(142, 15, 29, .25), transparent 70%),
    radial-gradient(35% 40% at 82% 30%, rgba(142, 15, 29, .25), transparent 70%),
    repeating-linear-gradient(90deg, rgba(0, 0, 0, .55) 0 18px, rgba(255, 255, 255, .025) 26px, rgba(0, 0, 0, .45) 44px, rgba(0, 0, 0, .6) 62px),
    linear-gradient(180deg, #120507 0%, #0b0405 55%, #050506 100%);
  filter: blur(.4px); animation: breathe 14s ease-in-out infinite alternate; }
@keyframes breathe { to { transform: scale(1.03); filter: blur(.4px) brightness(1.08); } }
.cs-vignette { position: absolute; inset: 0; z-index: 8; pointer-events: none; background: radial-gradient(120% 90% at 50% 45%, transparent 45%, rgba(0, 0, 0, .78) 100%); }
.cs-grain { position: absolute; inset: -50%; z-index: 9; pointer-events: none; opacity: .07; mix-blend-mode: overlay;
  background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='160' height='160'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2' stitchTiles='stitch'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>");
  animation: grain 1.2s steps(6) infinite; }
@keyframes grain { 0% { transform: translate(0, 0); } 50% { transform: translate(-3%, 2%); } 100% { transform: translate(2%, -3%); } }

/* Spotlights from the ceiling */
.cs-lights { position: absolute; inset: 0; z-index: 3; pointer-events: none; mix-blend-mode: screen; }
.cs-light { position: absolute; top: -12%; width: 34vw; height: 120vh; transform-origin: 50% 0; filter: blur(22px);
  background: linear-gradient(180deg, rgba(255, 214, 150, .34), rgba(255, 190, 120, .1) 45%, transparent 75%);
  clip-path: polygon(44% 0, 56% 0, 100% 100%, 0 100%); animation: flicker 9s ease-in-out infinite alternate; }
.cs-light:nth-child(1) { left: 2%; transform: rotate(8deg); animation-delay: -2s; }
.cs-light:nth-child(2) { left: 33%; transform: rotate(-2deg); opacity: .8; }
.cs-light:nth-child(3) { left: 64%; transform: rotate(-9deg); animation-delay: -5s; }
@keyframes flicker { 0% { opacity: .75; } 40% { opacity: .95; } 100% { opacity: .82; } }

/* Rails */
.cs-rail { position: absolute; left: 0; right: 0; z-index: 2; transform-style: preserve-3d; will-change: transform; }
.cs-rail .rod { position: absolute; left: -5%; right: -5%; top: 0; height: 7px; border-radius: 4px; z-index: 2;
  background: linear-gradient(180deg, #fff1c4 0%, #d9b45e 30%, #8a6a24 70%, #3a2a0a 100%); box-shadow: 0 2px 10px rgba(0, 0, 0, .6), 0 0 22px rgba(240, 212, 138, .25); }
.cs-track { position: absolute; top: 0; left: 0; display: flex; align-items: flex-start; gap: var(--gap); padding-left: var(--gap); will-change: transform; animation: drift var(--dur) linear infinite; }
.cs-rail.back .cs-track { animation-direction: reverse; }
@keyframes drift { from { transform: translateX(0); } to { transform: translateX(-50%); } }
.cs-item { position: relative; flex: 0 0 auto; width: var(--w); transform-origin: 50% 0; animation: sway var(--sway, 7s) ease-in-out infinite alternate; animation-delay: var(--d, 0s); }
@keyframes sway { from { transform: rotate(calc(var(--r, 0deg) - 1.6deg)); } to { transform: rotate(calc(var(--r, 0deg) + 1.6deg)); } }
.cs-hanger { display: block; width: 58%; margin: -10px auto -6%; position: relative; z-index: 1; filter: drop-shadow(0 2px 3px rgba(0, 0, 0, .6)); }
.cs-item img { display: block; width: 100%; height: auto; filter: drop-shadow(0 26px 30px rgba(0, 0, 0, .75)) drop-shadow(0 0 1px rgba(0, 0, 0, .6)) saturate(.92) contrast(1.04); user-select: none; -webkit-user-drag: none; }
.cs-rail.front { top: 17vh; --w: clamp(170px, 15vw, 250px); --gap: calc(var(--w) * -0.5); --dur: 240s; }
.cs-rail.back { top: 9vh; z-index: 1; --w: clamp(100px, 9vw, 150px); --gap: calc(var(--w) * -0.4); --dur: 340s; filter: blur(2.6px) brightness(.5) saturate(.8); opacity: .9; }
.cs-rail.back .rod { height: 4px; opacity: .7; }
/* Polished floor and the front rail's reflection */
.cs-floor { position: absolute; left: 0; right: 0; bottom: 0; height: 30vh; z-index: 4; pointer-events: none;
  background: linear-gradient(180deg, rgba(7, 7, 8, 0) 0%, rgba(7, 7, 8, .55) 18%, rgba(10, 6, 7, .92) 100%),
    radial-gradient(60% 80% at 50% 0%, rgba(201, 162, 74, .10), transparent 70%); border-top: 1px solid rgba(240, 212, 138, .08); }
.cs-reflect { position: absolute; left: 0; right: 0; bottom: 0; height: 30vh; z-index: 3; overflow: hidden; pointer-events: none; opacity: .22;
  -webkit-mask-image: linear-gradient(180deg, #000, transparent 75%); mask-image: linear-gradient(180deg, #000, transparent 75%); filter: blur(3px); }
.cs-reflect .cs-rail { top: 0; height: calc(var(--w) * 1.32); transform: scaleY(-1); transform-origin: 50% 50%; }

/* Haze and gold dust */
.cs-haze { position: absolute; inset: -20%; z-index: 5; pointer-events: none; opacity: .5;
  background: radial-gradient(40% 25% at 30% 60%, rgba(255, 220, 190, .07), transparent 70%), radial-gradient(35% 25% at 70% 40%, rgba(255, 210, 170, .06), transparent 70%);
  animation: haze 40s ease-in-out infinite alternate; }
@keyframes haze { to { transform: translate(6%, -4%) scale(1.1); } }
.cs-dust { position: absolute; inset: 0; z-index: 6; pointer-events: none; }
.cs-dust i { position: absolute; width: 3px; height: 3px; border-radius: 50%; background: radial-gradient(circle, #ffe7a8, rgba(255, 220, 140, 0)); opacity: 0; animation: mote var(--t) linear infinite; animation-delay: var(--dl); }
@keyframes mote { 0% { transform: translate(0, 0) scale(.6); opacity: 0; } 15% { opacity: .9; } 85% { opacity: .6; } 100% { transform: translate(var(--x), -38vh) scale(1.2); opacity: 0; } }

/* Centre: frosted glass panel */
.cs-center { position: absolute; inset: 0; z-index: 10; display: grid; place-items: center; padding: 24px; pointer-events: none; }
.cs-card { pointer-events: auto; width: min(470px, calc(100vw - 28px)); min-width: 0; text-align: center; padding: clamp(24px, 3.4vw, 38px) clamp(20px, 3.6vw, 42px); position: relative;
  background: linear-gradient(180deg, rgba(14, 12, 13, .42), rgba(8, 7, 8, .58)); -webkit-backdrop-filter: blur(22px) saturate(140%); backdrop-filter: blur(22px) saturate(140%);
  border: 1px solid rgba(242, 240, 235, .12); border-radius: 22px; box-shadow: 0 40px 120px -20px rgba(0, 0, 0, .9), inset 0 1px 0 rgba(242, 240, 235, .14), 0 0 80px -30px rgba(142, 15, 29, .7);
  opacity: 0; transform: translateY(14px) scale(.985); animation: rise 1.6s .4s cubic-bezier(.2, .7, .2, 1) forwards; }
.cs-card::before { content: ""; position: absolute; left: 18%; right: 18%; top: -1px; height: 1px; background: linear-gradient(90deg, transparent, var(--gold), transparent); }
@keyframes rise { to { opacity: 1; transform: none; } }
.cs-mark { width: 40px; height: auto; color: var(--gold); fill: currentColor; margin: 0 auto 14px; display: block; filter: drop-shadow(0 0 18px rgba(201, 162, 74, .35)); }
.cs-word { width: min(190px, 60%); height: auto; color: var(--ink); fill: currentColor; display: block; margin: 0 auto 14px; }
.cs-kicker { margin: 0 0 16px; display: inline-flex; align-items: center; gap: 10px; padding: 7px 14px; border-radius: 999px; border: 1px solid rgba(201, 162, 74, .35);
  font: 700 10px/1 Syncopate, sans-serif; letter-spacing: .28em; text-transform: uppercase; color: var(--gold); }
.cs-kicker::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: var(--gold); box-shadow: 0 0 10px var(--gold); }
h1 { margin: 0 0 12px; font: 700 clamp(30px, 4.6vw, 54px)/1.02 Syncopate, sans-serif; letter-spacing: .06em; text-transform: uppercase;
  background: linear-gradient(100deg, #f2f0eb 0%, #f2f0eb 40%, #f6dc96 50%, #f2f0eb 60%, #f2f0eb 100%); background-size: 250% 100%;
  -webkit-background-clip: text; background-clip: text; color: transparent; animation: sheen 7s ease-in-out 2s infinite; }
@keyframes sheen { 0% { background-position: 100% 0; } 55%, 100% { background-position: -50% 0; } }
.cs-sub { margin: 0 auto 20px; max-width: 38ch; color: var(--muted); font-size: 14px; }
.cs-sub em { font-style: normal; color: var(--ink); }
form { display: grid; gap: 12px; text-align: left; min-width: 0; }
.cs-row { display: flex; border: 1px solid rgba(242, 240, 235, .16); border-radius: 999px; background: rgba(7, 7, 8, .55); padding: 5px; transition: border-color .3s, box-shadow .3s; }
.cs-row:focus-within { border-color: rgba(201, 162, 74, .7); box-shadow: 0 0 0 4px rgba(201, 162, 74, .12); }
.cs-row input { flex: 1; min-width: 0; border: 0; background: transparent; color: var(--ink); font: inherit; padding: 10px 16px; outline: none; }
.cs-row input::placeholder { color: rgba(242, 240, 235, .4); }
.cs-row button { border: 0; border-radius: 999px; padding: 0 22px; min-height: 44px; cursor: pointer; color: #fff; background: linear-gradient(180deg, #a3192a, var(--ox)); box-shadow: 0 8px 28px -8px rgba(142, 15, 29, .9);
  font: 700 10px/1 Syncopate, sans-serif; letter-spacing: .2em; text-transform: uppercase; transition: transform .2s, filter .2s; white-space: nowrap; }
.cs-row button:hover { filter: brightness(1.15); } .cs-row button:active { transform: scale(.97); } .cs-row button[disabled] { opacity: .6; cursor: progress; }
.cs-consent { display: flex; gap: 10px; align-items: flex-start; font-size: 12px; color: var(--muted); cursor: pointer; padding: 0 6px; }
.cs-consent input { margin: 2px 0 0; accent-color: var(--ox); width: 16px; height: 16px; flex: 0 0 auto; }
.cs-msg { min-height: 20px; margin: 2px 0 0; font-size: 13px; text-align: center; color: var(--gold-2); }
.cs-msg.bad { color: #ff9b9b; }
.cs-foot { margin-top: 12px; font: 400 10px/1.6 Syncopate, sans-serif; letter-spacing: .26em; text-transform: uppercase; color: rgba(242, 240, 235, .45); }
.cs-foot a { color: var(--gold); text-decoration: none; }
.sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
@media (max-width: 760px) {
  /* Phones: the rack fills the top of the screen, the panel sits low */
  .cs-rail.front { top: 9vh; --w: 150px; --dur: 170s; }
  .cs-rail.back { top: 4vh; --w: 92px; --dur: 240s; }
  .cs-center { place-items: end center; padding: 14px 14px calc(14px + env(safe-area-inset-bottom)); }
  .cs-card { padding: 20px 18px 16px; border-radius: 20px; }
  .cs-mark { width: 30px; margin-bottom: 8px; }
  .cs-word { width: 150px; margin-bottom: 10px; }
  .cs-kicker { font-size: 8.5px; padding: 6px 12px; margin-bottom: 10px; }
  h1 { font-size: clamp(22px, 7.4vw, 30px); margin-bottom: 8px; }
  .cs-sub { font-size: 13px; margin-bottom: 14px; }
  .cs-consent { font-size: 11px; }
  .cs-row { flex-direction: column; border-radius: 16px; gap: 6px; min-width: 0; }
  .cs-row button { min-height: 46px; padding: 0 16px; }
  .cs-foot { display: none; }
}
@media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation: none !important; } .cs-card { opacity: 1; transform: none; } }
</style>
</head>
<body>
<div class="cs" aria-hidden="true">
  <div class="cs-wall"></div>
  <div class="cs-lights"><span class="cs-light"></span><span class="cs-light"></span><span class="cs-light"></span></div>
  <div class="cs-rail back" data-rail="back"><div class="rod"></div><div class="cs-track"></div></div>
  <div class="cs-rail front" data-rail="front"><div class="rod"></div><div class="cs-track"></div></div>
  <div class="cs-reflect"><div class="cs-rail front" data-rail="reflect"><div class="cs-track"></div></div></div>
  <div class="cs-floor"></div>
  <div class="cs-haze"></div>
  <div class="cs-dust"></div>
  <div class="cs-grain"></div>
  <div class="cs-vignette"></div>
</div>

<main class="cs-center">
  <section class="cs-card" aria-labelledby="cs-title">
    <?php echo $mark; // phpcs:ignore WordPress.Security.EscapeOutput -- the brand's own SVG file ?>
    <span class="sr">Schermo</span>
    <?php echo $word; // phpcs:ignore WordPress.Security.EscapeOutput ?>
    <p class="cs-kicker"><?php esc_html_e('Milano · Est. 2021', 'schermo'); ?></p>
    <h1 id="cs-title"><?php esc_html_e('Coming soon', 'schermo'); ?></h1>
    <p class="cs-sub"><em><?php esc_html_e('The art of dressing.', 'schermo'); ?></em> <?php esc_html_e('Our collections are being hung. Leave your email and we will tell you the moment the doors open.', 'schermo'); ?></p>
    <form data-notify novalidate>
      <div class="cs-row">
        <label class="sr" for="cs-email"><?php esc_html_e('Email address', 'schermo'); ?></label>
        <input id="cs-email" type="email" name="email" autocomplete="email" required placeholder="<?php esc_attr_e('Your email', 'schermo'); ?>">
        <button type="submit"><?php esc_html_e('Notify me', 'schermo'); ?></button>
      </div>
      <label class="cs-consent"><input type="checkbox" name="consent" value="1" required> <span><?php esc_html_e('Email me when Schermo opens, and about new collections. I can unsubscribe at any time.', 'schermo'); ?></span></label>
      <p class="cs-msg" role="status" aria-live="polite"></p>
    </form>
    <p class="cs-foot"><?php echo $insta !== '' && wp_http_validate_url($insta) ? '<a href="' . esc_url($insta) . '" rel="noopener" target="_blank">Instagram</a> · ' : ''; ?>schermo.co</p>
  </section>
</main>

<script>
(() => {
  'use strict';
  const pieces = <?php echo wp_json_encode(array_map(static fn ($p) => ['n' => $p[0], 'u' => wp_make_link_relative($p[1])], $pieces)); ?>;
  const hanger = '<svg class="cs-hanger" viewBox="0 0 120 46" aria-hidden="true"><defs><linearGradient id="hg" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#fff1c4"/><stop offset=".45" stop-color="#d5ad55"/><stop offset="1" stop-color="#7a5a18"/></linearGradient></defs><path d="M60 16c0-6 6-6 6-10.5S62.5 0 60 0s-6 1.8-6 5" fill="none" stroke="url(#hg)" stroke-width="2.4" stroke-linecap="round"/><path d="M60 16 6 40c-3 1.4-2 4.5 1 4.5h106c3 0 4-3.1 1-4.5z" fill="none" stroke="url(#hg)" stroke-width="3" stroke-linejoin="round"/></svg>';
  // A new order on every visit (Fisher–Yates), then split between the two rails.
  const list = pieces.slice();
  for (let i = list.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [list[i], list[j]] = [list[j], list[i]]; }
  const half = Math.ceil(list.length / 2);
  const rows = { front: list.slice(0, half), back: list.slice(half) };
  rows.reflect = rows.front;
  const fill = (name) => {
    const rail = document.querySelector(`[data-rail="${name}"] .cs-track`);
    if (!rail || !rows[name].length) return;
    const make = (p, k) => {
      const el = document.createElement('figure');
      el.className = 'cs-item'; el.style.margin = '0';
      el.style.setProperty('--r', (Math.random() * 3 - 1.5).toFixed(2) + 'deg');
      el.style.setProperty('--d', (-Math.random() * 8).toFixed(2) + 's');
      el.style.setProperty('--sway', (6 + Math.random() * 4).toFixed(2) + 's');
      el.innerHTML = hanger + `<img src="${p.u}" alt="" decoding="async" loading="${k < 10 ? 'eager' : 'lazy'}" width="768" height="960">`;
      return el;
    };
    // Twice over, so the slow drift loops without a seam.
    const frag = document.createDocumentFragment();
    [...rows[name], ...rows[name]].forEach((p, k) => frag.appendChild(make(p, k)));
    rail.appendChild(frag);
  };
  ['back', 'front', 'reflect'].forEach(fill);
  // Gold dust
  const dust = document.querySelector('.cs-dust');
  for (let i = 0; i < 46; i++) {
    const m = document.createElement('i');
    m.style.left = (Math.random() * 100).toFixed(1) + '%'; m.style.top = (55 + Math.random() * 50).toFixed(1) + '%';
    m.style.setProperty('--t', (10 + Math.random() * 14).toFixed(1) + 's'); m.style.setProperty('--dl', (-Math.random() * 20).toFixed(1) + 's');
    m.style.setProperty('--x', (Math.random() * 120 - 60).toFixed(0) + 'px');
    const s = 1 + Math.random() * 2.6; m.style.width = m.style.height = s.toFixed(1) + 'px';
    dust.appendChild(m);
  }
  // Gentle depth on pointer move (the rails and the light shift a little, the back rail less).
  const still = matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!still) {
    const front = document.querySelector('.cs > .cs-rail.front'), back = document.querySelector('.cs > .cs-rail.back'), lights = document.querySelector('.cs-lights');
    let tx = 0, ty = 0, x = 0, y = 0;
    addEventListener('pointermove', (e) => { tx = (e.clientX / innerWidth - .5); ty = (e.clientY / innerHeight - .5); }, { passive: true });
    const tick = () => { x += (tx - x) * .05; y += (ty - y) * .05;
      front.style.transform = `translate3d(${(-x * 26).toFixed(2)}px, ${(-y * 10).toFixed(2)}px, 0)`;
      back.style.transform = `translate3d(${(-x * 10).toFixed(2)}px, ${(-y * 4).toFixed(2)}px, 0)`;
      lights.style.transform = `translate3d(${(x * 18).toFixed(2)}px, 0, 0)`;
      requestAnimationFrame(tick); };
    requestAnimationFrame(tick);
  }
  // Notify me → the Schermo list (consent is recorded by the site).
  const form = document.querySelector('[data-notify]'), msg = form.querySelector('.cs-msg'), btn = form.querySelector('button');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = form.email.value.trim();
    msg.className = 'cs-msg bad';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { msg.textContent = 'Please enter a valid email address.'; form.email.focus(); return; }
    if (!form.consent.checked) { msg.textContent = 'Please tick the box so we may email you.'; return; }
    btn.disabled = true; msg.className = 'cs-msg'; msg.textContent = '…';
    try {
      const r = await fetch('/wp-json/scm/v1/newsletter', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email, consent: 1 }) });
      const d = await r.json().catch(() => ({}));
      if (!r.ok || !d.ok) throw new Error(d.error || 'Something went wrong. Please try again.');
      form.querySelector('.cs-row').style.display = 'none'; form.querySelector('.cs-consent').style.display = 'none';
      msg.textContent = 'You are on the list. We will email you the moment the doors open.' + (d.code ? ` Your welcome code: ${d.code} — ${d.percent}% off your first order.` : '');
    } catch (err) { msg.className = 'cs-msg bad'; msg.textContent = err.message; btn.disabled = false; }
  });
})();
</script>
</body>
</html>
