/** Read a design token (space-separated RGB in globals.css) as a CSS colour GSAP can tween. */
export function tokenColor(name: string, alpha = 1): string {
  if (typeof window === "undefined") return "rgba(0,0,0,1)";
  const raw = getComputedStyle(document.documentElement).getPropertyValue(`--${name}`).trim();
  const [r, g, b] = raw.split(/\s+/).map(Number);
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

/** Same token as a 0–1 RGB triple, for WebGL uniforms. */
export function tokenRGB(name: string): [number, number, number] {
  if (typeof window === "undefined") return [0, 0, 0];
  const raw = getComputedStyle(document.documentElement).getPropertyValue(`--${name}`).trim();
  const [r, g, b] = raw.split(/\s+/).map(Number);
  return [r / 255, g / 255, b / 255];
}
