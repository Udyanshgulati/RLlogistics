/**
 * One light source and one physical scale for every illustration.
 * Light comes from the top-left (~45°): highlights on top/left faces, shade and cast shadows to the bottom-right.
 */
export const LIGHT = {
  /** Unit vector pointing *towards* the light, in SVG space (y down). */
  dir: { x: -0.707, y: -0.707 },
  /** Cast shadows fall this far right per unit of object height. */
  shadowSkew: 0.35,
  highlight: "rgba(255,255,255,0.22)",
  shade: "rgba(0,0,0,0.28)",
  contactShadow: 0.42,
} as const;

/** World scale for the side-view yard: 34 px per metre (40 ft container ≈ 12.2 m ≈ 410 px). */
export const PX_PER_M = 34;
export const m = (metres: number) => metres * PX_PER_M;
