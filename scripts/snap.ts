/**
 * Visual test harness: scrolls the homepage in 5% steps at three viewports, screenshots every step,
 * tiles them into contact sheets and records a slow full-scroll video.
 *
 *   npm run build && npm start          # prod server on :3000 (not dev)
 *   npm run snap                         # all viewports + video
 *   npm run snap -- --only=1440 --no-video
 *
 * Env: BASE (default http://localhost:3000), SETTLE ms after each scroll (default 1200 — scene timelines
 * use scrub: 1, so they need ~1s to catch up; 300ms would capture them mid-catch-up).
 */
import { chromium, type Browser, type Page } from "playwright";
import sharp from "sharp";
import fs from "node:fs";
import path from "node:path";

const BASE = process.env.BASE ?? "http://localhost:3000";
const SETTLE = Number(process.env.SETTLE ?? 1200);
const OUT = path.resolve("snaps");
const STEPS = 20; // 0%, 5%, … 100%

const VIEWPORTS = [
  { name: "1440", width: 1440, height: 900, dpr: 2 },
  { name: "1280", width: 1280, height: 800, dpr: 1 },
  { name: "390", width: 390, height: 844, dpr: 3 },
];

const args = process.argv.slice(2);
const only = args.find((a) => a.startsWith("--only="))?.split("=")[1]?.split(",");
const withVideo = !args.includes("--no-video");

type Report = { viewport: string; console: string[]; pageErrors: string[]; longTasks: number[]; overflowX: boolean };

// Passed as a string: tsx/esbuild would otherwise inject a `__name` helper the browser doesn't have.
const frames = (page: Page) => page.evaluate("new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)))");

async function open(browser: Browser, vp: (typeof VIEWPORTS)[number], video?: string) {
  const ctx = await browser.newContext({
    viewport: { width: vp.width, height: vp.height },
    deviceScaleFactor: vp.dpr,
    ...(video ? { recordVideo: { dir: video, size: { width: vp.width, height: vp.height } } } : {}),
  });
  const page = await ctx.newPage();
  const report: Report = { viewport: vp.name, console: [], pageErrors: [], longTasks: [], overflowX: false };
  page.on("console", (m) => {
    if (m.type() === "error" || m.type() === "warning") report.console.push(`${m.type()}: ${m.text().slice(0, 240)}`);
  });
  page.on("pageerror", (e) => report.pageErrors.push(e.message));
  await page.addInitScript(() => {
    (window as unknown as { __longTasks: number[] }).__longTasks = [];
    try {
      new PerformanceObserver((list) => {
        for (const e of list.getEntries()) (window as unknown as { __longTasks: number[] }).__longTasks.push(Math.round(e.duration));
      }).observe({ type: "longtask", buffered: true });
    } catch {}
  });
  await page.goto(`${BASE}/?test=1`, { waitUntil: "load", timeout: 90_000 });
  // Wait for the intro loader to finish and the hero to play in.
  await page.waitForSelector('[role="status"][aria-label^="Loading"]', { state: "detached", timeout: 60_000 }).catch(() => {});
  await page.waitForTimeout(2500);
  return { ctx, page, report };
}

async function shoot(browser: Browser, vp: (typeof VIEWPORTS)[number]) {
  const dir = path.join(OUT, vp.name);
  fs.mkdirSync(dir, { recursive: true });
  const { ctx, page, report } = await open(browser, vp);
  const total = await page.evaluate(() => document.documentElement.scrollHeight - window.innerHeight);
  const files: string[] = [];
  for (let i = 0; i <= STEPS; i++) {
    await page.evaluate((y) => window.scrollTo(0, y), Math.round((total * i) / STEPS));
    await frames(page);
    await page.waitForTimeout(SETTLE);
    const file = path.join(dir, `${String(i).padStart(2, "0")}.png`);
    await page.screenshot({ path: file, timeout: 120_000 });
    files.push(file);
    process.stdout.write(`  ${vp.name} ${i * 5}%\r`);
  }
  report.overflowX = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
  report.longTasks = await page.evaluate(() => (window as unknown as { __longTasks: number[] }).__longTasks);
  await ctx.close();
  await contactSheet(files, path.join(OUT, `contact-${vp.name}.png`), vp);
  return report;
}

async function contactSheet(files: string[], out: string, vp: (typeof VIEWPORTS)[number]) {
  const cols = vp.width < 600 ? 7 : 5;
  const tileW = vp.width < 600 ? 220 : 400;
  const tileH = Math.round((tileW * vp.height) / vp.width);
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
  await sharp({
    create: { width: cols * (tileW + gap) - gap, height: rows * (tileH + label + gap) - gap, channels: 3, background: "#ffffff" },
  })
    .composite(tiles.flatMap((t) => [{ input: t.tag, left: t.x, top: t.y }, { input: t.img, left: t.x, top: t.y + label }]))
    .png()
    .toFile(out);
  console.log(`  contact sheet → ${path.relative(process.cwd(), out)}`);
}

async function video(browser: Browser) {
  const vp = VIEWPORTS[1];
  const tmp = path.join(OUT, "_video");
  fs.rmSync(tmp, { recursive: true, force: true });
  const { ctx, page } = await open(browser, vp, tmp);
  const total = await page.evaluate(() => document.documentElement.scrollHeight - window.innerHeight);
  // Slow wheel scroll so the scrubbed timelines are recorded the way a person scrolls.
  for (let y = 0; y < total; y += 30) {
    await page.mouse.wheel(0, 30);
    await page.waitForTimeout(40);
  }
  await page.waitForTimeout(2000);
  const vid = page.video();
  await ctx.close();
  if (vid) fs.renameSync(await vid.path(), path.join(OUT, "scroll.webm"));
  fs.rmSync(tmp, { recursive: true, force: true });
  console.log("  video → snaps/scroll.webm");
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  // SwiftShader flags let WebGL render on GPU-less CI machines; harmless with a GPU.
  const browser = await chromium.launch({ args: ["--use-angle=swiftshader", "--enable-unsafe-swiftshader", "--ignore-gpu-blocklist"] });
  const reports: Report[] = [];
  for (const vp of VIEWPORTS.filter((v) => !only || only.includes(v.name))) {
    console.log(`▶ ${vp.name} (${vp.width}×${vp.height} @${vp.dpr}x)`);
    reports.push(await shoot(browser, vp));
  }
  if (withVideo) await video(browser);
  await browser.close();
  fs.writeFileSync(path.join(OUT, "report.json"), JSON.stringify(reports, null, 2));
  for (const r of reports) {
    const long = r.longTasks.filter((t) => t > 50);
    console.log(
      `${r.viewport}: ${r.pageErrors.length} page errors, ${r.console.length} console warnings/errors, ` +
        `${long.length} long tasks >50ms (max ${Math.max(0, ...r.longTasks)}ms), overflow-x: ${r.overflowX}`,
    );
  }
})();
