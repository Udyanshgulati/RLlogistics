// Samples land points on a lat-row grid and writes them as Int16 pairs (lat*100, lng*100).
// Run: node scripts/build-globe-points.mjs  → public/globe/land-points.bin
import { readFileSync, writeFileSync } from "node:fs";
import { feature } from "topojson-client";

const STEP = 0.8; // degrees between rows / dots
const topo = JSON.parse(readFileSync(new URL("../node_modules/world-atlas/land-50m.json", import.meta.url)));
const land = feature(topo, topo.objects.land);

// Flatten to polygons with bboxes; planar ray-casting in lng/lat is fine away from the poles.
const polys = [];
for (const f of land.features) {
  const g = f.geometry;
  const list = g.type === "Polygon" ? [g.coordinates] : g.coordinates;
  for (const rings of list) {
    let [x0, y0, x1, y1] = [180, 90, -180, -90];
    for (const [x, y] of rings[0]) {
      x0 = Math.min(x0, x); x1 = Math.max(x1, x);
      y0 = Math.min(y0, y); y1 = Math.max(y1, y);
    }
    polys.push({ rings, box: [x0, y0, x1, y1] });
  }
}

const inRing = (x, y, ring) => {
  let inside = false;
  for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    const [xi, yi] = ring[i];
    const [xj, yj] = ring[j];
    if (yi > y !== yj > y && x < ((xj - xi) * (y - yi)) / (yj - yi) + xi) inside = !inside;
  }
  return inside;
};
const onLand = (x, y) =>
  polys.some(
    ({ rings, box }) =>
      x >= box[0] && x <= box[2] && y >= box[1] && y <= box[3] &&
      inRing(x, y, rings[0]) && !rings.slice(1).some((h) => inRing(x, y, h)),
  );

const out = [];
for (let lat = -58; lat <= 82; lat += STEP) {
  const n = Math.max(1, Math.round((360 * Math.cos((lat * Math.PI) / 180)) / STEP));
  for (let i = 0; i < n; i++) {
    const lng = -180 + (i / n) * 360;
    if (onLand(lng, lat)) out.push(Math.round(lat * 100), Math.round(lng * 100));
  }
}
writeFileSync(new URL("../public/globe/land-points.bin", import.meta.url), Buffer.from(new Int16Array(out).buffer));
console.log(`${out.length / 2} land points`);
