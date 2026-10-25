/** Procedural textures drawn once to a canvas and cached as data URLs (no image downloads). */

const cache = new Map<string, string>();

function canvas(w: number, h: number) {
  const c = document.createElement("canvas");
  c.width = w;
  c.height = h;
  return [c, c.getContext("2d")!] as const;
}

/** Seeded PRNG so textures look identical on every visit. */
function rng(seed: number) {
  return () => {
    seed = (seed * 16807) % 2147483647;
    return (seed - 1) / 2147483646;
  };
}

/** Deep-water surface: soft swell bands + scattered white caustic glints. Tileable-ish, 1024². */
export function waterTexture() {
  if (cache.has("water")) return cache.get("water")!;
  const S = 1024;
  const [c, g] = canvas(S, S);
  const r = rng(7);
  g.fillStyle = "#0a3a9e";
  g.fillRect(0, 0, S, S);
  for (let i = 0; i < 70; i++) {
    const x = r() * S, y = r() * S, rad = 120 + r() * 260;
    const grd = g.createRadialGradient(x, y, 0, x, y, rad);
    const dark = r() > 0.5;
    grd.addColorStop(0, dark ? "rgba(4,30,110,0.5)" : "rgba(40,110,230,0.35)");
    grd.addColorStop(1, "rgba(0,0,0,0)");
    g.fillStyle = grd;
    // Draw every blob at its wrapped copies too, so the tile repeats without visible seams (no "boxes").
    for (const ox of [-S, 0, S])
      for (const oy of [-S, 0, S]) {
        if (x + ox + rad < 0 || x + ox - rad > S || y + oy + rad < 0 || y + oy - rad > S) continue;
        g.save();
        g.translate(ox, oy);
        g.fillRect(x - rad, y - rad, rad * 2, rad * 2);
        g.restore();
      }
  }
  g.strokeStyle = "rgba(255,255,255,0.10)";
  for (let i = 0; i < 900; i++) {
    const len = 6 + r() * 26;
    const x = r() * (S - len), y = 8 + r() * (S - 16);
    g.lineWidth = 0.6 + r() * 1.4;
    g.beginPath();
    g.moveTo(x, y);
    g.quadraticCurveTo(x + len / 2, y - 3 - r() * 4, x + len, y);
    g.stroke();
  }
  const url = c.toDataURL("image/jpeg", 0.82);
  cache.set("water", url);
  return url;
}

/**
 * A soft cloud bank built from layered radial puffs on a transparent canvas.
 * Puffs stay inside a padded area and a final elliptical mask fades every edge to alpha 0,
 * so the bank can never show a straight (clipped) edge when it is scaled or moved.
 */
export function cloudTexture(seed: number, w = 1600, h = 800) {
  const key = `cloud-${seed}-${w}x${h}`;
  if (cache.has(key)) return cache.get(key)!;
  const [c, g] = canvas(w, h);
  const r = rng(seed);
  const maxR = h * 0.2;
  for (let i = 0; i < 110; i++) {
    const rad = maxR * (0.35 + r() * 0.65);
    const x = rad + w * 0.06 + r() * (w * 0.88 - rad * 2);
    const y = h * 0.38 + (r() - 0.5) * h * 0.3 - Math.sin((x / w) * Math.PI) * h * 0.1;
    const grd = g.createRadialGradient(x, y - rad * 0.2, 0, x, y, rad);
    grd.addColorStop(0, "rgba(255,255,255,0.95)");
    grd.addColorStop(0.55, "rgba(246,249,253,0.6)");
    grd.addColorStop(1, "rgba(220,230,245,0)");
    g.fillStyle = grd;
    g.beginPath();
    g.arc(x, y, rad, 0, Math.PI * 2);
    g.fill();
  }
  // Feather all four edges to transparent.
  g.globalCompositeOperation = "destination-in";
  g.save();
  g.translate(w / 2, h / 2);
  g.scale(w / h, 1);
  const mask = g.createRadialGradient(0, 0, h * 0.2, 0, 0, h * 0.5);
  mask.addColorStop(0, "rgba(0,0,0,1)");
  mask.addColorStop(1, "rgba(0,0,0,0)");
  g.fillStyle = mask;
  g.fillRect(-h / 2, -h / 2, h, h);
  g.restore();
  g.globalCompositeOperation = "source-over";
  const url = c.toDataURL("image/png");
  cache.set(key, url);
  return url;
}

/** White foam wake for the top-down ship (bow up): a V that fans out behind the stern. */
export function wakeTexture() {
  if (cache.has("wake")) return cache.get("wake")!;
  // Drawn in 600×1600 design units at 1.5× resolution so the wake stays crisp at the ship's close-up scale.
  const [c, g] = canvas(900, 2400);
  g.scale(1.5, 1.5);
  const r = rng(3);
  // One soft dot sprite, stamped many times — far cheaper than a canvas blur filter.
  const [dot, dg] = canvas(64, 64);
  const grd = dg.createRadialGradient(32, 32, 0, 32, 32, 32);
  grd.addColorStop(0, "rgba(255,255,255,1)");
  grd.addColorStop(0.5, "rgba(255,255,255,0.45)");
  grd.addColorStop(1, "rgba(255,255,255,0)");
  dg.fillStyle = grd;
  dg.fillRect(0, 0, 64, 64);
  const stamp = (x: number, y: number, size: number, alpha: number) => {
    g.globalAlpha = alpha;
    g.drawImage(dot, x - size / 2, y - size / 2, size, size);
  };
  for (let i = 0; i < 1400; i++) {
    const t = r();
    const y = 80 + t * 1500;
    const spread = 70 + t * 230;
    const side = r() > 0.5 ? 1 : -1;
    stamp(300 + side * spread * (0.65 + r() * 0.45), y, 18 + r() * 34, 0.1 + (1 - t) * 0.35);
  }
  // churn behind the stern + bow wave hugging the hull
  for (let i = 0; i < 420; i++) {
    const y = 1080 + r() * 480;
    stamp(300 + (r() - 0.5) * (60 + (y - 1080) * 0.35), y, 16 + r() * 30, 0.25 + r() * 0.35);
  }
  for (let i = 0; i < 420; i++) {
    const side = r() > 0.5 ? 1 : -1;
    stamp(300 + side * (118 + r() * 16), 60 + r() * 1000, 10 + r() * 18, 0.3 + r() * 0.4);
  }
  g.globalAlpha = 1;
  const url = c.toDataURL("image/png");
  cache.set("wake", url);
  return url;
}

/**
 * Runs the tasks once, each in its own idle callback, when `el` comes within ~2 screens of the viewport.
 * Keeps texture painting out of the initial page load and splits it into short tasks.
 */
export function whenNear(el: Element, tasks: Array<() => void>) {
  let started = false;
  const ric =
    (window as Window & { requestIdleCallback?: (f: () => void, o?: { timeout: number }) => number }).requestIdleCallback ??
    ((f: () => void) => window.setTimeout(f, 16));
  const runFrom = (i: number) => {
    if (i >= tasks.length) return;
    ric(() => {
      tasks[i]();
      runFrom(i + 1);
    }, { timeout: 800 });
  };
  const io = new IntersectionObserver(
    (entries) => {
      if (started || !entries.some((e) => e.isIntersecting)) return;
      started = true;
      io.disconnect();
      runFrom(0);
    },
    { rootMargin: "200% 0px" },
  );
  io.observe(el);
  return () => io.disconnect();
}
