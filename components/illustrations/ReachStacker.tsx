import { Wheel } from "./Wheel";
import { ContactShadow } from "./Shading";
import { PX_PER_M } from "@/lib/light";

/**
 * Side-view reach-stacker facing right, drawn in local units and placed in the world with `STACKER.scale`
 * (local 55 px/m → world 34 px/m). Local origin = rear wheel line on the ground.
 * Animated parts (data-part): stacker (placed by the rig), boom (rotates at its pivot), inner (telescopic
 * extension, slides out of the outer section), cylinder (line that lengthens as the boom rises),
 * spreader (counter-rotated to stay level), wheels.
 */
const LOCAL_PX_PER_M = 55;
export const STACKER = {
  scale: PX_PER_M / LOCAL_PX_PER_M,
  pivot: { x: 120, y: -150 },
  boomLength: 560,
  /** Spreader contact face sits this far below the boom tip (local units). */
  hang: 68,
  /** 40 ft spreader: 12.2 m. */
  spreaderWidth: Math.round(12.2 * LOCAL_PX_PER_M),
  wheelR: [40, 54] as const,
  wheelX: [92, 404] as const,
  cylinderBase: { x: 300, y: -112 },
  cylinderAt: 250,
};

export function ReachStacker({ bodyImg, boomImg }: { bodyImg?: string; boomImg?: string }) {
  const { pivot, boomLength: L, spreaderWidth: SW } = STACKER;
  return (
    <g data-part="stacker">
      <ContactShadow x={260} y={3} w={600} h={18} opacity={0.8} />
      {bodyImg ? (
        <image href={bodyImg} x={0} y={-250} width={480} height={250} preserveAspectRatio="xMidYMax meet" />
      ) : (
        <>
          {/* chassis */}
          <path d="M10 -64 H480 L470 -128 H210 L190 -150 H10 Z" fill="#1E8FB8" />
          <path d="M10 -64 H480 L470 -128 H210 L190 -150 H10 Z" fill="url(#sh-panel)" />
          <path d="M10 -64 H480 L476 -86 H10 Z" fill="#14709a" />
          <path d="M190 -150 L210 -128 H470" stroke="#fff" strokeOpacity={0.35} strokeWidth={2} fill="none" />
          {/* counterweight */}
          <path d="M0 -70 H118 V-162 Q118 -172 108 -172 H10 Q0 -172 0 -162 Z" fill="#1A7EA5" />
          <path d="M0 -70 H118 V-162 Q118 -172 108 -172 H10 Q0 -172 0 -162 Z" fill="url(#sh-panel)" />
          <rect x={6} y={-150} width={104} height={8} fill="#0f5d80" opacity={0.6} />
          <path d="M2 -164 Q2 -170 10 -170 H108" stroke="#fff" strokeOpacity={0.35} strokeWidth={2} fill="none" />
          {/* wheel arches */}
          <path d="M44 -64 A48 48 0 0 1 140 -64" fill="#0c4f6d" />
          <path d="M344 -64 A60 60 0 0 1 464 -64" fill="#0c4f6d" />
          {/* cab */}
          <g>
            <path d="M232 -128 V-232 Q232 -240 240 -240 H318 Q326 -240 328 -232 L338 -128 Z" fill="#1E8FB8" />
            <path d="M232 -128 V-232 Q232 -240 240 -240 H318 Q326 -240 328 -232 L338 -128 Z" fill="url(#sh-panel)" />
            <path d="M244 -150 V-224 H314 L322 -150 Z" fill="url(#sh-glass)" />
            <path d="M250 -156 L270 -218 H286 L262 -156 Z" fill="#fff" opacity={0.2} />
            <rect x={226} y={-246} width={110} height={8} rx={2} fill="#0f5d80" />
            <rect x={286} y={-150} width={4} height={60} fill="#0f5d80" />
          </g>
          {/* warning beacon */}
          <rect x={270} y={-256} width={14} height={10} rx={3} fill="#f2a43a" />
        </>
      )}
      {/* lift cylinder: barrel + chrome rod (endpoints set at runtime so it extends as the boom rises) */}
      <line data-part="cylinder" x1={300} y1={-112} x2={360} y2={-220} stroke="url(#sh-chrome)" strokeWidth={12} strokeLinecap="round" />
      <line data-part="cylinder-barrel" x1={300} y1={-112} x2={330} y2={-166} stroke="#2a3240" strokeWidth={20} strokeLinecap="round" />
      {/* boom: outer section + telescopic inner section */}
      <g data-part="boom" transform={`translate(${pivot.x} ${pivot.y})`}>
        <g data-part="inner">
          <rect x={140} y={-15} width={430} height={30} fill="#2a3240" />
          <rect x={140} y={-15} width={430} height={30} fill="url(#sh-panel)" />
          <rect x={150} y={-15} width={420} height={2} fill="#fff" opacity={0.25} />
          <g data-part="tip" transform={`translate(${L + 10} 0)`}>
            <path d="M-16 -22 H18 L26 6 L6 26 H-16 Z" fill="#1d2430" />
            <circle r={6} fill="url(#sh-chrome)" />
          </g>
        </g>
        {boomImg ? (
          <image href={boomImg} x={-40} y={-30} width={620} height={60} preserveAspectRatio="none" />
        ) : (
          <>
            <rect x={-40} y={-24} width={540} height={48} rx={4} fill="#1d2430" />
            <rect x={-40} y={-24} width={540} height={48} rx={4} fill="url(#sh-panel)" />
            <rect x={-36} y={-24} width={532} height={2} fill="#fff" opacity={0.3} />
            <rect x={-40} y={20} width={540} height={4} fill="#000" opacity={0.35} />
            <text x={120} y={6} fontSize={14} fontFamily="var(--font-mono), monospace" fill="#9fb7c9" letterSpacing={3}>
              RL · 45T
            </text>
          </>
        )}
        <circle r={14} fill="url(#sh-chrome)" />
        <circle r={6} fill="#1d2430" />
      </g>
      {/* spreader (positioned at the boom tip at runtime) with twistlocks at each corner */}
      <g data-part="spreader">
        <rect x={-8} y={0} width={16} height={40} fill="#1d2430" />
        <rect x={-34} y={26} width={68} height={16} rx={3} fill="#2a3240" />
        <g transform={`translate(${-SW / 2} 40)`}>
          <rect width={SW} height={28} fill="#f2c230" />
          {Array.from({ length: Math.floor(SW / 24) }, (_, i) => (
            <path key={i} d={`M${i * 24} 28 L${i * 24 + 12} 0 H${i * 24 + 22} L${i * 24 + 10} 28 Z`} fill="#16191f" opacity={0.85} />
          ))}
          <rect width={SW} height={28} fill="url(#sh-panel)" />
          <rect width={SW} height={5} fill="#16191f" opacity={0.6} />
          <rect x={-6} y={-2} width={14} height={34} fill="#16191f" />
          <rect x={SW - 8} y={-2} width={14} height={34} fill="#16191f" />
          {/* twistlocks */}
          <rect x={-2} y={30} width={8} height={8} fill="#c9a227" />
          <rect x={SW - 6} y={30} width={8} height={8} fill="#c9a227" />
        </g>
      </g>
      {/* wheels on contact shadows */}
      <ContactShadow x={STACKER.wheelX[0] + 4} y={2} w={STACKER.wheelR[0] * 2.5} h={10} />
      <ContactShadow x={STACKER.wheelX[1] + 4} y={2} w={STACKER.wheelR[1] * 2.5} h={10} />
      <g transform={`translate(${STACKER.wheelX[0]} ${-STACKER.wheelR[0]})`}>
        <Wheel r={STACKER.wheelR[0]} part="wheel" />
      </g>
      <g transform={`translate(${STACKER.wheelX[1]} ${-STACKER.wheelR[1]})`}>
        <Wheel r={STACKER.wheelR[1]} part="wheel" />
      </g>
    </g>
  );
}
