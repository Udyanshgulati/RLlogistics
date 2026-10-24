import { Wheel } from "./Wheel";

/**
 * Side-view reach-stacker facing right. Local origin = rear wheel line on the ground.
 * Animated parts (data-part): body, boom (rotates at its pivot), inner (telescopic extension),
 * cylinder (line), spreader (counter-rotated to stay level), wheels.
 */
export const STACKER = {
  pivot: { x: 120, y: -150 },
  boomLength: 560,
  /** Spreader contact face sits this far below the boom tip. */
  hang: 68,
  spreaderWidth: 420,
  wheelR: [48, 54] as const,
  wheelX: [96, 404] as const,
  cylinderBase: { x: 300, y: -112 },
  cylinderAt: 250,
};

/** Art boxes for override PNGs (local units): body x 0..480 · y −250..0 (no wheels); boom x −40..580 · y −30..30, pivot at 0,0. */
export function ReachStacker({ bodyImg, boomImg }: { bodyImg?: string; boomImg?: string }) {
  const { pivot, boomLength: L, spreaderWidth: SW } = STACKER;
  return (
    <g data-part="stacker">
      <ellipse cx={250} cy={2} rx={290} ry={9} fill="#0a0d14" opacity={0.12} />
      {bodyImg ? (
        <image href={bodyImg} x={0} y={-250} width={480} height={250} preserveAspectRatio="xMidYMax meet" />
      ) : (
        <>
      {/* chassis */}
      <path d="M10 -64 H480 L470 -128 H210 L190 -150 H10 Z" fill="#1E8FB8" />
      <path d="M10 -64 H480 L476 -86 H10 Z" fill="#14709a" />
      {/* counterweight */}
      <path d="M0 -70 H118 V-162 Q118 -172 108 -172 H10 Q0 -172 0 -162 Z" fill="#1A7EA5" />
      <rect x={6} y={-150} width={104} height={8} fill="#0f5d80" opacity={0.6} />
      {/* cab */}
      <g>
        <path d="M232 -128 V-232 Q232 -240 240 -240 H318 Q326 -240 328 -232 L338 -128 Z" fill="#1E8FB8" />
        <path d="M244 -150 V-224 H314 L322 -150 Z" fill="#1d2430" />
        <path d="M250 -156 V-218 H286 V-156 Z" fill="#8fc3dc" opacity={0.35} />
        <rect x={226} y={-246} width={110} height={8} rx={2} fill="#0f5d80" />
      </g>
        </>
      )}
      {/* lift cylinder (endpoints set at runtime) */}
      <line data-part="cylinder" x1={300} y1={-112} x2={360} y2={-220} stroke="#b6bcc6" strokeWidth={16} strokeLinecap="round" />
      {/* boom */}
      <g data-part="boom" transform={`translate(${pivot.x} ${pivot.y})`}>
        <g data-part="inner">
          <rect x={140} y={-15} width={430} height={30} fill="#2a3240" />
          <rect x={150} y={-15} width={420} height={6} fill="#3a4454" />
          <g data-part="tip" transform={`translate(${L + 10} 0)`}>
            <path d="M-16 -22 H18 L26 6 L6 26 H-16 Z" fill="#1d2430" />
            <circle r={6} fill="#b6bcc6" />
          </g>
        </g>
        {boomImg ? (
          <image href={boomImg} x={-40} y={-30} width={620} height={60} preserveAspectRatio="none" />
        ) : (
          <>
        <rect x={-40} y={-24} width={540} height={48} rx={4} fill="#1d2430" />
        <rect x={-40} y={-24} width={540} height={10} fill="#323b4a" />
        <text x={120} y={6} fontSize={14} fontFamily="var(--font-mono), monospace" fill="#9fb7c9" letterSpacing={3}>
          RL · 45T
        </text>
          </>
        )}
        <circle r={14} fill="#b6bcc6" />
        <circle r={6} fill="#1d2430" />
      </g>
      {/* spreader (positioned in world space at runtime) */}
      <g data-part="spreader">
        <rect x={-8} y={0} width={16} height={40} fill="#1d2430" />
        <rect x={-34} y={26} width={68} height={16} rx={3} fill="#2a3240" />
        <g transform={`translate(${-SW / 2} 40)`}>
          <rect width={SW} height={28} fill="#f2c230" />
          {Array.from({ length: Math.floor(SW / 24) }, (_, i) => (
            <path key={i} d={`M${i * 24} 28 L${i * 24 + 12} 0 H${i * 24 + 22} L${i * 24 + 10} 28 Z`} fill="#16191f" opacity={0.85} />
          ))}
          <rect width={SW} height={5} fill="#16191f" opacity={0.6} />
          <rect x={-6} y={-2} width={14} height={32} fill="#16191f" />
          <rect x={SW - 8} y={-2} width={14} height={32} fill="#16191f" />
        </g>
      </g>
      {/* wheels */}
      <g transform={`translate(${STACKER.wheelX[0]} ${-STACKER.wheelR[0]})`}>
        <Wheel r={STACKER.wheelR[0]} part="wheel" />
      </g>
      <g transform={`translate(${STACKER.wheelX[1]} ${-STACKER.wheelR[1]})`}>
        <Wheel r={STACKER.wheelR[1]} part="wheel" />
      </g>
    </g>
  );
}
