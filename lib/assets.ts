import "server-only";
import fs from "node:fs";
import path from "node:path";
import { ASSET_SLOTS, type AssetOverrides, type AssetSlot } from "./asset-slots";

/** Checks /public/assets at build/render time and returns URLs for the artwork that exists. */
export function getAssetOverrides(): AssetOverrides {
  const out: AssetOverrides = {};
  for (const [slot, file] of Object.entries(ASSET_SLOTS) as [AssetSlot, string][]) {
    if (fs.existsSync(path.join(process.cwd(), "public", "assets", file))) out[slot] = `/assets/${file}`;
  }
  return out;
}
