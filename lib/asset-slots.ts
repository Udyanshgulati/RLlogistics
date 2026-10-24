/**
 * Optional artwork that replaces the coded illustrations when present in /public/assets/.
 * Paths are relative to /public/assets. See README → "Asset overrides" for the art-box of each slot.
 */
export const ASSET_SLOTS = {
  heroRoad: "hero-road.jpg",
  stackerBody: "yard/reach-stacker-body.png",
  stackerBoom: "yard/reach-stacker-boom.png",
  containerWhite: "yard/container-white.png",
  truckSide: "truck-side.png",
  truckTop: "truck-top.png",
  shipTop: "ship-top.png",
  planeTop: "plane-top.png",
  cloud1: "clouds-1.png",
  cloud2: "clouds-2.png",
  cloud3: "clouds-3.png",
  ocean: "ocean.mp4",
} as const;

export type AssetSlot = keyof typeof ASSET_SLOTS;
/** Public URL for each slot whose file exists, otherwise undefined (use the coded illustration). */
export type AssetOverrides = Partial<Record<AssetSlot, string>>;
