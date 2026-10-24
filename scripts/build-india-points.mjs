// Samples India's outline on a lat/lng grid for the /network map.
// Run: node scripts/build-india-points.mjs  → content/india-points.json  ([lng, lat] pairs, 2 decimals)
import { readFileSync, writeFileSync } from "node:fs";
import { feature } from "topojson-client";

const STEP = 0.42;
const topo = JSON.parse(readFileSync(new URL("../node_modules/world-atlas/countries-50m.json", import.meta.url)));
const india = feature(topo, topo.objects.countries).features.find((f) => f.id === "356");
const polys = india.geometry.type === "Polygon" ? [india.geometry.coordinates] : india.geometry.coordinates;

const inRing = (x, y, ring) => {
  let inside = false;
  for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    const [xi, yi] = ring[i];
    const [xj, yj] = ring[j];
    if (yi > y !== yj > y && x < ((xj - xi) * (y - yi)) / (yj - yi) + xi) inside = !inside;
  }
  return inside;
};
const inside = (x, y) => polys.some((rings) => inRing(x, y, rings[0]) && !rings.slice(1).some((h) => inRing(x, y, h)));

const out = [];
for (let lat = 6; lat <= 37.5; lat += STEP) {
  for (let lng = 67; lng <= 98; lng += STEP) {
    if (inside(lng, lat)) out.push([+lng.toFixed(2), +lat.toFixed(2)]);
  }
}
writeFileSync(new URL("../content/india-points.json", import.meta.url), JSON.stringify(out));
console.log(`${out.length} points`);
