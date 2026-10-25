/**
 * Mobile test harness: real device profiles (touch, isMobile, device UA), scrolled with real *touch* swipes
 * (CDP Input.dispatchTouchEvent start → moves → end; headless ignores synthesizeScrollGesture and
 * mouse.wheel / scrollTo don't reproduce touch bugs), screenshot every 5% of the page,
 * an in-page layout check on every shot, a contact sheet per device and a slow swipe-scroll video.
 *
 *   npm run build && npm start
 *   npm run snap:mobile                              # all devices
 *   npm run snap:mobile -- --only=iphone-13,pixel-7 --no-video
 *   npm run snap:mobile -- --path=/contact           # any page
 *
 * Output: snaps/mobile/<device>/NN.png + NN.json (checker results), snaps/mobile/<device>/swipe.webm,
 * snaps/mobile/<device>-sheet.png, snaps/mobile/report.json.
 *
 * Note: Chromium with each device's viewport, DPR, UA and touch emulation. WebKit isn't installed here, so iOS
 * Safari-only behaviour (address-bar resize, rubber-banding, momentum feel) still needs a real-device check.
 */
import { chromium, devices, type Browser, type Page, type CDPSession } from "playwright";
import sharp from "sharp";
import fs from "node:fs";
import path from "node:path";

const BASE = process.env.BASE ?? "http://localhost:3000";
const SETTLE = Number(process.env.SETTLE ?? 1200);
const STEPS = 20;

type Device = { name: string; width: number; height: number; dpr: number; ua: string };
const ua = (d: string) => devices[d].userAgent;
const DEVICES: Device[] = [
  { name: "android-360", width: 360, height: 740, dpr: 3, ua: ua("Galaxy S9+") },
  { name: "iphone-se", width: 375, height: 667, dpr: 2, ua: ua("iPhone SE") },
  { name: "iphone-13", width: 390, height: 844, dpr: 3, ua: ua("iPhone 13") },
  { name: "iphone-15-pro-max", width: 430, height: 932, dpr: 3, ua: ua("iPhone 15 Pro Max") },
  { name: "pixel-7", width: 412, height: 915, dpr: 2.625, ua: ua("Pixel 7") },
  { name: "ipad-mini", width: 768, height: 1024, dpr: 2, ua: ua("iPad Mini") },
  // Opt-in with --only: landscape phone.
  { name: "iphone-13-land", width: 844, height: 390, dpr: 3, ua: ua("iPhone 13 landscape") },
];
const DEFAULT_DEVICES = DEVICES.slice(0, 6).map((d) => d.name);

const args = process.argv.slice(2);
const only = args.find((a) => a.startsWith("--only="))?.split("=")[1]?.split(",");
const pagePath = args.find((a) => a.startsWith("--path="))?.split("=")[1] ?? "/";
const withVideo = !args.includes("--no-video");
// --dpr=2 caps the device pixel ratio (3x shots of a long page can exhaust a CPU-only browser).
const dprCap = Number(args.find((a) => a.startsWith("--dpr="))?.split("=")[1] ?? Infinity);
const slug = pagePath === "/" ? "" : pagePath.replace(/\//g, "_");
const OUT = path.resolve("snaps", "mobile" + slug);

/** In-page layout checker. Passed as a string so tsx doesn't inject helpers the browser lacks. */
const CHECK = `(() => {
  const W = innerWidth, H = innerHeight;
  const desc = (el) => {
    const cls = (typeof el.className === "string" ? el.className : el.getAttribute("class") || "").trim().split(/\\s+/).filter(Boolean).slice(0, 3).join(".");
    const data = [...el.attributes].filter((a) => a.name.startsWith("data-")).map((a) => "[" + a.name + "]").slice(0, 2).join("");
    const txt = (el.innerText || "").trim().replace(/\\s+/g, " ").slice(0, 32);
    return el.tagName.toLowerCase() + (cls ? "." + cls : "") + data + (txt ? ' "' + txt + '"' : "");
  };
  const visible = (el) => {
    if (!el.checkVisibility({ opacityProperty: true, visibilityProperty: true })) return false;
    const r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0;
  };
  const inView = (r) => r.bottom > 0 && r.top < H && r.right > 0 && r.left < W;
  // Clipped by an ancestor that hides overflow and itself sits inside the viewport.
  const clipped = (el) => {
    for (let p = el.parentElement; p && p !== document.body; p = p.parentElement) {
      const cs = getComputedStyle(p);
      // Clipped or inside a horizontal scroller (a swipe carousel's peeking card is intentional).
      if (/(hidden|clip|auto|scroll)/.test(cs.overflowX) || /(hidden|clip)/.test(cs.overflow)) {
        const r = p.getBoundingClientRect();
        if (r.left >= -1 && r.right <= W + 1) return true;
      }
    }
    return false;
  };
  const all = [...document.body.querySelectorAll("*")].filter((el) => !(el instanceof SVGElement && el.ownerSVGElement));

  const overflowX = document.documentElement.scrollWidth > W;
  const offenders = all.filter((el) => {
    if (!visible(el)) return false;
    const r = el.getBoundingClientRect();
    return inView(r) && (r.right > W + 1 || r.left < -1) && !clipped(el);
  }).slice(0, 12).map((el) => { const r = el.getBoundingClientRect(); return desc(el) + " [" + Math.round(r.left) + "→" + Math.round(r.right) + "]"; });

  const textOverflow = [...document.querySelectorAll("h1,h2,h3,h4,p,a,button,li")].filter((el) =>
    visible(el) && inView(el.getBoundingClientRect()) && el.scrollWidth > el.clientWidth + 1 && getComputedStyle(el).display !== "inline"
  ).slice(0, 10).map((el) => desc(el) + " (" + el.scrollWidth + " > " + el.clientWidth + ")");

  // Overlaps between text blocks / buttons / images that aren't nested in each other.
  // Content blocks only: the fixed header floats over content by design, so it is left out.
  const inFixed = (el) => { for (let p = el; p && p !== document.body; p = p.parentElement) if (getComputedStyle(p).position === "fixed") return true; return false; };
  const blocks = [...document.querySelectorAll("h1,h2,h3,p,a,button,img,[data-feature],li")].filter((el) => visible(el) && inView(el.getBoundingClientRect()) && !inFixed(el));
  // Visible part of an element: its box intersected with every ancestor that clips overflow.
  const shown = (el) => {
    const r = el.getBoundingClientRect();
    let l = r.left, t = r.top, rt = r.right, b = r.bottom;
    for (let p = el.parentElement; p && p !== document.body; p = p.parentElement) {
      const cs = getComputedStyle(p);
      if (cs.overflow !== "visible" || cs.overflowX !== "visible" || cs.overflowY !== "visible") {
        const q = p.getBoundingClientRect();
        l = Math.max(l, q.left); t = Math.max(t, q.top); rt = Math.min(rt, q.right); b = Math.min(b, q.bottom);
      }
    }
    return rt - l > 0 && b - t > 0 ? { left: l, top: t, right: rt, bottom: b, width: rt - l, height: b - t } : null;
  };
  const overlaps = [];
  for (let i = 0; i < blocks.length; i++) for (let j = i + 1; j < blocks.length; j++) {
    const a = blocks[i], b = blocks[j];
    if (a.contains(b) || b.contains(a)) continue;
    const ra = shown(a), rb = shown(b);
    if (!ra || !rb) continue;
    const ix = Math.min(ra.right, rb.right) - Math.max(ra.left, rb.left);
    const iy = Math.min(ra.bottom, rb.bottom) - Math.max(ra.top, rb.top);
    if (ix > 4 && iy > 4 && ix * iy > 0.15 * Math.min(ra.width * ra.height, rb.width * rb.height)) overlaps.push(desc(a) + "  ×  " + desc(b));
    if (overlaps.length >= 10) break;
  }

  const taps = [...document.querySelectorAll("a,button,input,select,textarea,[role=button]")].filter((el) => {
    if (!visible(el)) return false;
    const r = el.getBoundingClientRect();
    return inView(r) && (r.width < 44 || r.height < 44);
  }).slice(0, 12).map((el) => { const r = el.getBoundingClientRect(); return desc(el) + " " + Math.round(r.width) + "×" + Math.round(r.height); });

  const smallText = [...document.querySelectorAll("p,li,blockquote,dd")].filter((el) => {
    if (!visible(el) || !inView(el.getBoundingClientRect())) return false;
    return parseFloat(getComputedStyle(el).fontSize) < 14 && (el.innerText || "").trim().length > 24;
  }).slice(0, 10).map((el) => desc(el) + " " + getComputedStyle(el).fontSize);

  // Globe: the hero canvas has painted pixels, or its fallback image is showing.
  let globe = null;
  const gw = document.querySelector("[data-m-globe]");
  if (gw && inView(gw.getBoundingClientRect())) {
    const c = gw.querySelector("canvas");
    let painted = 0;
    try { const d = c.getContext("2d").getImageData(0, 0, c.width, c.height).data; for (let i = 3; i < d.length; i += 40) if (d[i] > 0) painted++; } catch (e) {}
    const img = gw.querySelector("img");
    globe = painted > 200 || (img && img.complete && img.naturalWidth > 0 && getComputedStyle(img).opacity !== "0");
  }
  // Blank screen: no visible text, image, svg or canvas anywhere in the viewport.
  const content = [...document.querySelectorAll("h1,h2,h3,p,li,a,button,img,svg,canvas,blockquote")].filter((el) => visible(el) && inView(el.getBoundingClientRect())).length;
  const triggers = typeof window.__M_TRIGGERS__ === "function" ? window.__M_TRIGGERS__() : null;
  const desktopScenes = document.querySelectorAll("[data-scene]").length;
  return { scrollY: Math.round(scrollY), overflowX, scrollWidth: document.documentElement.scrollWidth, offenders, textOverflow, overlaps, taps, smallText, globe, blank: content === 0, triggers, desktopScenes };
})()`;

type Check = { scrollY: number; overflowX: boolean; scrollWidth: number; offenders: string[]; textOverflow: string[]; overlaps: string[]; taps: string[]; smallText: string[]; globe: boolean | null; blank: boolean; triggers: number | null; desktopScenes: number };
type Report = { device: string; path: string; console: string[]; pageErrors: string[]; longTasks: number[]; reached: number[]; checks: Check[] };

const frames = (page: Page) => page.evaluate("new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)))");

/** One finger swipe of `dist` px (positive = scroll down), 20 px per move like a real drag. */
async function swipe(cdp: CDPSession, d: Device, dist: number, stepMs = 0) {
  const x = Math.round(d.width / 2);
  let y = dist > 0 ? Math.round(d.height * 0.85) : Math.round(d.height * 0.15);
  await cdp.send("Input.dispatchTouchEvent", { type: "touchStart", touchPoints: [{ x, y }] });
  const n = Math.max(1, Math.round(Math.abs(dist) / (stepMs ? 20 : 60)));
  for (let k = 0; k < n; k++) {
    y -= dist / n;
    await cdp.send("Input.dispatchTouchEvent", { type: "touchMove", touchPoints: [{ x, y: Math.round(y) }] });
    if (stepMs) await new Promise((r) => setTimeout(r, stepMs));
  }
  await cdp.send("Input.dispatchTouchEvent", { type: "touchEnd", touchPoints: [] });
}

/** Touch-swipe until scrollY is within a few px of `target` (each swipe is at most 70% of the screen). */
async function swipeTo(page: Page, cdp: CDPSession, d: Device, target: number) {
  for (let k = 0; k < 200; k++) {
    const y = (await page.evaluate("scrollY")) as number;
    const delta = target - y;
    if (Math.abs(delta) < 4) return y;
    await swipe(cdp, d, Math.max(-d.height * 0.7, Math.min(d.height * 0.7, delta)));
    await page.waitForTimeout(60);
  }
  return (await page.evaluate("scrollY")) as number;
}

async function open(browser: Browser, d: Device, video?: string) {
  const ctx = await browser.newContext({
    viewport: { width: d.width, height: d.height },
    screen: { width: d.width, height: d.height },
    deviceScaleFactor: Math.min(d.dpr, dprCap),
    isMobile: true,
    hasTouch: true,
    userAgent: d.ua,
    ...(video ? { recordVideo: { dir: video, size: { width: d.width, height: d.height } } } : {}),
  });
  const page = await ctx.newPage();
  const cdp = await ctx.newCDPSession(page);
  const report: Report = { device: d.name, path: pagePath, console: [], pageErrors: [], longTasks: [], reached: [], checks: [] };
  page.on("console", (m) => {
    if (m.type() === "error" || m.type() === "warning") report.console.push(`${m.type()}: ${m.text().slice(0, 240)}`);
  });
  page.on("pageerror", (e) => report.pageErrors.push(e.message));
  await page.addInitScript(
    `window.__longTasks=[];try{new PerformanceObserver(l=>{for(const e of l.getEntries())window.__longTasks.push(Math.round(e.duration))}).observe({type:"longtask",buffered:true})}catch{}`,
  );
  await page.goto(`${BASE}${pagePath}?test=1`, { waitUntil: "load", timeout: 90_000 });
  await page.waitForTimeout(2500);
  return { ctx, page, cdp, report };
}

async function shoot(browser: Browser, d: Device) {
  const dir = path.join(OUT, d.name);
  fs.rmSync(dir, { recursive: true, force: true });
  fs.mkdirSync(dir, { recursive: true });
  const { ctx, page, cdp, report } = await open(browser, d);
  const total = (await page.evaluate("document.documentElement.scrollHeight - innerHeight")) as number;
  const files: string[] = [];
  for (let i = 0; i <= STEPS; i++) {
    const reached = await swipeTo(page, cdp, d, Math.round((total * i) / STEPS));
    report.reached.push(reached);
    await frames(page);
    await page.waitForTimeout(SETTLE);
    const nn = String(i).padStart(2, "0");
    const file = path.join(dir, `${nn}.png`);
    await page.screenshot({ path: file, timeout: 120_000 });
    const check = (await page.evaluate(CHECK)) as Check;
    fs.writeFileSync(path.join(dir, `${nn}.json`), JSON.stringify(check, null, 2));
    report.checks.push(check);
    files.push(file);
    process.stdout.write(`  ${d.name} ${i * 5}%   \r`);
  }
  report.longTasks = (await page.evaluate("window.__longTasks")) as number[];
  await ctx.close();
  await contactSheet(files, path.join(OUT, `${d.name}-sheet.png`), d);
  return report;
}

async function contactSheet(files: string[], out: string, d: Device) {
  const cols = 7;
  const tileW = d.width > d.height ? 300 : 200;
  const tileH = Math.round((tileW * d.height) / d.width);
  const label = 22;
  const gap = 6;
  const rows = Math.ceil(files.length / cols);
  const tiles = await Promise.all(
    files.map(async (f, i) => {
      const img = await sharp(f).resize(tileW, tileH).toBuffer();
      const tag = Buffer.from(
        `<svg width="${tileW}" height="${label}"><rect width="100%" height="100%" fill="#111"/><text x="6" y="16" font-family="monospace" font-size="13" fill="#fff">${path.basename(f)} · ${i * 5}%</text></svg>`,
      );
      return { img, tag, x: (i % cols) * (tileW + gap), y: Math.floor(i / cols) * (tileH + label + gap) };
    }),
  );
  await sharp({ create: { width: cols * (tileW + gap) - gap, height: rows * (tileH + label + gap) - gap, channels: 3, background: "#ffffff" } })
    .composite(tiles.flatMap((t) => [{ input: t.tag, left: t.x, top: t.y }, { input: t.img, left: t.x, top: t.y + label }]))
    .png()
    .toFile(out);
}

/** Slow swipe-scroll through the whole page, recorded. */
async function video(browser: Browser, d: Device) {
  const tmp = path.join(OUT, `_video-${d.name}`);
  fs.rmSync(tmp, { recursive: true, force: true });
  const { ctx, page, cdp } = await open(browser, d, tmp);
  const total = (await page.evaluate("document.documentElement.scrollHeight - innerHeight")) as number;
  let y = 0;
  for (let n = 0; n < 1000 && y < total - 4; n++) {
    await swipe(cdp, d, d.height * 0.3, 12);
    await page.waitForTimeout(120);
    y = (await page.evaluate("scrollY")) as number;
  }
  await page.waitForTimeout(1500);
  const vid = page.video();
  await ctx.close();
  if (vid) fs.renameSync(await vid.path(), path.join(OUT, d.name, "swipe.webm"));
  fs.rmSync(tmp, { recursive: true, force: true });
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch({ args: ["--use-angle=swiftshader", "--enable-unsafe-swiftshader", "--ignore-gpu-blocklist"] });
  const reportFile = path.join(OUT, "report.json");
  const prev: Report[] = fs.existsSync(reportFile) ? JSON.parse(fs.readFileSync(reportFile, "utf8")) : [];
  const reports = new Map(prev.map((r) => [r.device, r]));
  for (const d of DEVICES.filter((x) => (only ? only.includes(x.name) : DEFAULT_DEVICES.includes(x.name)))) {
    console.log(`▶ ${d.name} (${d.width}×${d.height} @${d.dpr}x) ${pagePath}`);
    const r = await shoot(browser, d);
    if (withVideo) await video(browser, d);
    reports.set(d.name, r);
    const n = (k: keyof Check) => r.checks.filter((c) => (Array.isArray(c[k]) ? (c[k] as string[]).length : c[k])).length;
    console.log(
      `  globe visible at top: ${r.checks[0]?.globe} · blank frames: ${r.checks.filter((c) => c.blank).length} · triggers: ${r.checks[0]?.triggers} · desktop scenes mounted: ${r.checks[0]?.desktopScenes}\n` +
      `  ${r.pageErrors.length} page errors · ${r.console.length} console · overflow-x in ${n("overflowX")}/21 · offenders ${n("offenders")} · text-overflow ${n("textOverflow")} · overlaps ${n("overlaps")} · small taps ${n("taps")} · small text ${n("smallText")} · long tasks >100ms: ${r.longTasks.filter((t) => t > 100).length}`,
    );
  }
  await browser.close();
  fs.writeFileSync(reportFile, JSON.stringify([...reports.values()], null, 2));
})();
