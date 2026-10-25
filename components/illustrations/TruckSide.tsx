import { Wheel } from "./Wheel";
import { ContactShadow } from "./Shading";
import { m } from "@/lib/light";

/**
 * Side-view tractor + 40 ft skeletal trailer facing right, drawn at real proportions (PX_PER_M = 34).
 * Local origin = trailer rear at ground level. The container slot is centred at x = TRUCK.slotX with its
 * floor at y = TRUCK.deckY. Tractor: steer axle + tandem drive (1.35 m apart); trailer: tight tri-axle bogie.
 * Pass `img` to swap the body for artwork (wheels stay vector so they keep spinning).
 */
export const TRUCK = { deckY: -m(1.42), slotX: m(6.8), length: m(17.6), wheelR: m(0.52) };

const R = TRUCK.wheelR;
const TRAILER_LEN = m(13.6);
const trailerAxles = [m(1.9), m(3.21), m(4.52)];
const driveAxles = [m(11.75), m(13.1)];
const steerAxle = m(16.25);
const CAB = { x0: m(14.7), x1: m(17.55), top: -m(3.9), sill: -m(1.05) };

export function TruckSide({ img }: { img?: string }) {
  const deck = TRUCK.deckY;
  return (
    <g data-part="truck">
      {/* long cast shadow + ambient occlusion under the whole rig (light from top-left → shadow drifts right) */}
      <ContactShadow x={m(9.2)} y={2} w={m(19)} h={12} opacity={0.75} />

      {img ? (
        <image href={img} x={0} y={CAB.top - m(0.6)} width={TRUCK.length} height={-(CAB.top - m(0.6))} preserveAspectRatio="xMidYMax meet" />
      ) : (
        <g>
          {/* ── Trailer (skeletal chassis) ── */}
          <rect x={0} y={deck} width={TRAILER_LEN} height={m(0.24)} fill="#2c3138" />
          <rect x={0} y={deck} width={TRAILER_LEN} height={m(0.24)} fill="url(#sh-panel)" />
          <rect x={0} y={deck} width={TRAILER_LEN} height={1.2} fill="#fff" opacity={0.25} />
          <rect x={m(0.2)} y={deck + m(0.24)} width={m(12.9)} height={m(0.32)} fill="#1b1f24" />
          {/* corner twistlocks */}
          {[m(0.35), m(6.4), m(12.7)].map((x) => (
            <rect key={x} x={x} y={deck - 3} width={10} height={4} rx={1} fill="#c9a227" />
          ))}
          {/* landing legs just behind the tractor */}
          <rect x={m(9.6)} y={deck + m(0.56)} width={m(0.18)} height={-(deck + m(0.56)) - m(0.12)} fill="#3a4049" />
          <rect x={m(9.45)} y={-m(0.14)} width={m(0.5)} height={m(0.1)} rx={1} fill="#3a4049" />
          {/* side underrun guard between legs and bogie */}
          <rect x={m(5.3)} y={-m(0.82)} width={m(4.1)} height={m(0.1)} fill="#aab1ba" />
          <rect x={m(5.3)} y={-m(0.6)} width={m(4.1)} height={m(0.1)} fill="#aab1ba" />
          {/* bogie suspension box + mudguard over the tri-axle */}
          <rect x={m(1.2)} y={deck + m(0.5)} width={m(4)} height={m(0.24)} fill="#20252b" />
          <path d={`M ${m(1.2)} ${-m(1.12)} Q ${m(3.2)} ${-m(1.36)} ${m(5.2)} ${-m(1.12)} V ${-m(1.02)} H ${m(1.2)} Z`} fill="#15181c" />
          <rect x={m(0.55)} y={-m(1.0)} width={m(0.08)} height={m(0.7)} fill="#111" />
          {/* marker lights along the side + tail light */}
          {[1, 3, 5, 7, 9, 11].map((k) => (
            <circle key={k} cx={m(k * 1.1)} cy={deck + m(0.12)} r={2} fill="#f2a43a" />
          ))}
          <rect x={-2} y={deck + 2} width={6} height={m(0.3)} rx={1} fill="#d8362b" />

          {/* ── Tractor ── */}
          {/* frame rail */}
          <rect x={m(11)} y={-m(1.12)} width={m(6.5)} height={m(0.3)} fill="#1b1f24" />
          {/* fifth wheel */}
          <path d={`M ${m(11.6)} ${-m(1.2)} h ${m(1.9)} l ${-m(0.15)} ${-m(0.12)} h ${-m(1.6)} z`} fill="#3a4049" />
          {/* drive-axle mudguard + flap */}
          <path d={`M ${m(11.05)} ${-m(1.05)} Q ${m(12.4)} ${-m(1.3)} ${m(13.8)} ${-m(1.05)} V ${-m(0.98)} H ${m(11.05)} Z`} fill="#15181c" />
          <rect x={m(10.9)} y={-m(1.0)} width={m(0.08)} height={m(0.68)} fill="#111" />
          {/* fuel tank + step between drive and steer axles */}
          <rect x={m(13.85)} y={-m(1.25)} width={m(1.45)} height={m(0.62)} rx={m(0.28)} fill="url(#sh-chrome)" />
          <rect x={m(13.85)} y={-m(1.25)} width={m(1.45)} height={m(0.62)} rx={m(0.28)} fill="none" stroke="#7d848c" strokeWidth={1} />
          <rect x={m(14.9)} y={-m(0.78)} width={m(0.55)} height={m(0.08)} fill="#3a4049" />

          {/* cab body */}
          <path
            d={`M ${CAB.x0} ${CAB.sill} V ${CAB.top + m(0.25)} Q ${CAB.x0} ${CAB.top} ${CAB.x0 + m(0.25)} ${CAB.top} H ${CAB.x1 - m(0.45)} Q ${CAB.x1 - m(0.15)} ${CAB.top} ${CAB.x1 - m(0.1)} ${CAB.top + m(0.3)} L ${CAB.x1} ${CAB.sill + m(0.1)} V ${-m(0.62)} H ${CAB.x0} Z`}
            fill="#23282f"
          />
          <path
            d={`M ${CAB.x0} ${CAB.sill} V ${CAB.top + m(0.25)} Q ${CAB.x0} ${CAB.top} ${CAB.x0 + m(0.25)} ${CAB.top} H ${CAB.x1 - m(0.45)} Q ${CAB.x1 - m(0.15)} ${CAB.top} ${CAB.x1 - m(0.1)} ${CAB.top + m(0.3)} L ${CAB.x1} ${CAB.sill + m(0.1)} V ${-m(0.62)} H ${CAB.x0} Z`}
            fill="url(#sh-panel)"
          />
          {/* roof fairing up to container height */}
          <path d={`M ${CAB.x0 + m(0.3)} ${CAB.top} L ${CAB.x0 + m(0.55)} ${CAB.top - m(0.42)} H ${CAB.x1 - m(0.7)} Q ${CAB.x1 - m(0.4)} ${CAB.top - m(0.42)} ${CAB.x1 - m(0.35)} ${CAB.top} Z`} fill="#2b3139" />
          <path d={`M ${CAB.x0 + m(0.55)} ${CAB.top - m(0.42)} H ${CAB.x1 - m(0.7)}`} stroke="#fff" strokeOpacity={0.25} strokeWidth={1.2} />
          {/* top-edge highlight and bottom bevel */}
          <path d={`M ${CAB.x0 + m(0.25)} ${CAB.top + 1} H ${CAB.x1 - m(0.45)}`} stroke="#fff" strokeOpacity={0.3} strokeWidth={1.2} />
          <rect x={CAB.x0} y={-m(0.66)} width={CAB.x1 - CAB.x0} height={1.2} fill="#000" opacity={0.5} />
          {/* door seam, handle, side window with reflection */}
          <path d={`M ${m(15.55)} ${CAB.top + m(0.35)} V ${CAB.sill}`} stroke="#0f1215" strokeWidth={1.2} />
          <path d={`M ${m(16.95)} ${CAB.top + m(0.35)} V ${CAB.sill}`} stroke="#0f1215" strokeWidth={1.2} />
          <rect x={m(16.55)} y={-m(2.15)} width={m(0.28)} height={m(0.06)} rx={1} fill="#9aa1aa" />
          <path d={`M ${m(15.7)} ${CAB.top + m(0.45)} H ${m(16.85)} L ${m(16.85)} ${-m(2.35)} H ${m(15.7)} Z`} fill="url(#sh-glass)" />
          <path d={`M ${m(15.85)} ${CAB.top + m(0.55)} L ${m(16.25)} ${CAB.top + m(0.55)} L ${m(15.85)} ${-m(2.6)} Z`} fill="#fff" opacity={0.22} />
          {/* windshield edge on the cab front */}
          <path d={`M ${CAB.x1 - m(0.32)} ${CAB.top + m(0.42)} L ${CAB.x1 - m(0.08)} ${CAB.top + m(0.55)} L ${CAB.x1 - m(0.02)} ${-m(2.35)} L ${CAB.x1 - m(0.26)} ${-m(2.35)} Z`} fill="url(#sh-glass)" />
          {/* mirror */}
          <rect x={CAB.x1 - m(0.05)} y={CAB.top + m(0.6)} width={m(0.1)} height={m(0.75)} fill="#1b1f24" />
          <rect x={CAB.x1 + m(0.02)} y={CAB.top + m(0.7)} width={m(0.16)} height={m(0.55)} rx={2} fill="#2b3139" />
          {/* grille, bumper, headlight */}
          <rect x={CAB.x1 - m(0.08)} y={-m(1.55)} width={m(0.12)} height={m(0.6)} fill="#151a1f" />
          <rect x={CAB.x1 - m(0.15)} y={-m(0.95)} width={m(0.25)} height={m(0.34)} rx={2} fill="#3a4049" />
          <rect x={CAB.x1 - m(0.04)} y={-m(1.3)} width={m(0.1)} height={m(0.16)} rx={1} fill="#ffe2a8" />
          {/* cab steps */}
          <rect x={m(15.65)} y={-m(0.55)} width={m(0.5)} height={m(0.06)} fill="#3a4049" />
          <text x={m(15.75)} y={-m(1.55)} fontSize={11} fontFamily="var(--font-mono), monospace" fill="#8d939b" letterSpacing={2}>
            RL
          </text>
        </g>
      )}

      {/* tyres sit on contact shadows; dual wheels on drive and trailer axles */}
      {[...trailerAxles, ...driveAxles, steerAxle].map((x) => (
        <ContactShadow key={`s${x}`} x={x + 3} y={1} w={R * 2.6} h={7} />
      ))}
      {[...trailerAxles, ...driveAxles].map((x) => (
        <g key={x} transform={`translate(${x} ${-R})`}>
          <Wheel r={R} part="truck-wheel" dual />
        </g>
      ))}
      <g transform={`translate(${steerAxle} ${-R})`}>
        <Wheel r={R} part="truck-wheel" />
      </g>
    </g>
  );
}
