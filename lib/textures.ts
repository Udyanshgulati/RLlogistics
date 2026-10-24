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
  const [c, g] = canvas(1024, 1024);
  const r = rng(7);
  g.fillStyle = "#0a3a9e";
  g.fillRect(0, 0, 1024, 1024);
  for (let i = 0; i < 70; i++) {
    const x = r() * 1024, y = r() * 1024, rad = 120 + r() * 260;
    const grd = g.createRadialGradient(x, y, 0, x, y, rad);
    const dark = r() > 0.5;
    grd.addColorStop(0, dark ? "rgba(4,30,110,0.5)" : "rgba(40,110,230,0.35)");
    grd.addColorStop(1, "rgba(0,0,0,0)");
    g.fillStyle = grd;
    g.fillRect(x - rad, y - rad, rad * 2, rad * 2);
  }
  g.strokeStyle = "rgba(255,255,255,0.10)";
  for (let i = 0; i < 900; i++) {
    const x = r() * 1024, y = r() * 1024, len = 6 + r() * 26;
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

/** A soft cloud bank built from layered radial puffs on a transparent canvas. */
export function cloudTexture(seed: number, w = 1400, h = 700) {
  const key = `cloud-${seed}-${w}x${h}`;
  if (cache.has(key)) return cache.get(key)!;
  const [c, g] = canvas(w, h);
  const r = rng(seed);
  for (let i = 0; i < 160; i++) {
    const x = w * (0.08 + r() * 0.84);
    const y = h * (0.3 + r() * 0.45) - Math.sin((x / w) * Math.PI) * h * 0.12;
    const rad = 50 + r() * 150;
    const grd = g.createRadialGradient(x, y - rad * 0.2, 0, x, y, rad);
    grd.addColorStop(0, "rgba(255,255,255,0.95)");
    grd.addColorStop(0.55, "rgba(246,249,253,0.65)");
    grd.addColorStop(1, "rgba(220,230,245,0)");
    g.fillStyle = grd;
    g.beginPath();
    g.arc(x, y, rad, 0, Math.PI * 2);
    g.fill();
  }
  const url = c.toDataURL("image/png");
  cache.set(key, url);
  return url;
}

/** White foam wake for the top-down ship (bow up): a V that fans out behind the stern. */
export function wakeTexture() {
  if (cache.has("wake")) return cache.get("wake")!;
  const [c, g] = canvas(600, 1600);
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
