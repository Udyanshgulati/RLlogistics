/**
 * Pixel diff of desktop snaps against a baseline: prints the share of changed pixels per frame and writes
 * red-highlighted diff images. Usage: tsx scripts/diff-desktop.ts <baselineDir> <currentDir> <outDir>
 * Time-based motion (globe spin, marquee, water drift) never matches exactly, so small % are expected there.
 */
import sharp from "sharp";
import fs from "node:fs";
import path from "node:path";

const [base, cur, out] = process.argv.slice(2);
fs.mkdirSync(out, { recursive: true });
(async () => {
  for (const f of fs.readdirSync(base).filter((x) => x.endsWith(".png")).sort()) {
    const a = await sharp(path.join(base, f)).raw().ensureAlpha().toBuffer({ resolveWithObject: true });
    const b = await sharp(path.join(cur, f)).raw().ensureAlpha().toBuffer({ resolveWithObject: true });
    if (a.info.width !== b.info.width || a.info.height !== b.info.height) {
      console.log(`${f}: size differs`);
      continue;
    }
    const diff = Buffer.alloc(a.data.length);
    let changed = 0;
    for (let i = 0; i < a.data.length; i += 4) {
      const d = Math.abs(a.data[i] - b.data[i]) + Math.abs(a.data[i + 1] - b.data[i + 1]) + Math.abs(a.data[i + 2] - b.data[i + 2]);
      if (d > 24) {
        changed++;
        diff[i] = 255; diff[i + 1] = 0; diff[i + 2] = 0; diff[i + 3] = 255;
      } else {
        const g = (a.data[i] + a.data[i + 1] + a.data[i + 2]) / 3;
        diff[i] = diff[i + 1] = diff[i + 2] = Math.round(g * 0.35 + 160); diff[i + 3] = 255;
      }
    }
    const pct = (100 * changed) / (a.data.length / 4);
    console.log(`${f}: ${pct.toFixed(2)}% pixels changed`);
    await sharp(diff, { raw: { width: a.info.width, height: a.info.height, channels: 4 } }).png().toFile(path.join(out, f));
  }
})();
