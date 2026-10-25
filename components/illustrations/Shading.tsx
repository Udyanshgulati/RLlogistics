import { LIGHT } from "@/lib/light";

/**
 * Shared SVG defs for the yard illustrations: panel shading that follows the global light,
 * a soft contact shadow and tyre gradients. Render once inside each <svg> that uses them.
 */
export function ShadingDefs() {
  return (
    <defs>
      <linearGradient id="sh-panel" x1="0" y1="0" x2="0.35" y2="1">
        <stop offset="0" stopColor="#fff" stopOpacity="0.2" />
        <stop offset="0.45" stopColor="#fff" stopOpacity="0" />
        <stop offset="1" stopColor="#000" stopOpacity="0.22" />
      </linearGradient>
      <linearGradient id="sh-glass" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0" stopColor="#dfe9f2" stopOpacity="0.95" />
        <stop offset="0.45" stopColor="#8fa8bd" stopOpacity="0.85" />
        <stop offset="0.55" stopColor="#5d7489" stopOpacity="0.9" />
        <stop offset="1" stopColor="#2a3a4a" stopOpacity="0.95" />
      </linearGradient>
      <radialGradient id="sh-contact">
        <stop offset="0" stopColor="#0b1f4d" stopOpacity={LIGHT.contactShadow} />
        <stop offset="0.6" stopColor="#0b1f4d" stopOpacity={LIGHT.contactShadow * 0.35} />
        <stop offset="1" stopColor="#0b1f4d" stopOpacity="0" />
      </radialGradient>
      <radialGradient id="sh-tyre" cx="0.4" cy="0.38" r="0.65">
        <stop offset="0" stopColor="#2b2b2b" />
        <stop offset="1" stopColor="#1a1a1a" />
      </radialGradient>
      <radialGradient id="sh-rim" cx="0.38" cy="0.35" r="0.7">
        <stop offset="0" stopColor="#e4e7eb" />
        <stop offset="0.7" stopColor="#a7adb5" />
        <stop offset="1" stopColor="#7c838c" />
      </radialGradient>
      <linearGradient id="sh-chrome" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" stopColor="#f2f4f6" />
        <stop offset="0.5" stopColor="#9aa1aa" />
        <stop offset="1" stopColor="#d5d9de" />
      </linearGradient>
    </defs>
  );
}

/** Soft elliptical ground shadow (ambient occlusion) centred at x, resting on y. */
export function ContactShadow({ x, y, w, h = 8, opacity = 1 }: { x: number; y: number; w: number; h?: number; opacity?: number }) {
  return <ellipse cx={x} cy={y} rx={w / 2} ry={h / 2} fill="url(#sh-contact)" opacity={opacity} />;
}
