import { Wheel } from "./Wheel";

/**
 * Side-view tractor + flatbed trailer facing right. Local origin = trailer rear at ground level.
 * The container slot is centred at x = TRUCK.slotX with its floor at y = TRUCK.deckY.
 */
export const TRUCK = { deckY: -118, slotX: 235, length: 660, wheelR: 30 };

const trailerWheels = [64, 126, 188];
const tractorWheels = [478, 612];

export function TruckSide() {
  return (
    <g data-part="truck">
      <ellipse cx={330} cy={2} rx={350} ry={9} fill="#0a0d14" opacity={0.14} />
      {/* trailer */}
      <rect x={0} y={-118} width={490} height={18} fill="#2a2f37" />
      <rect x={0} y={-100} width={490} height={10} fill="#16191f" />
      <rect x={20} y={-90} width={220} height={26} fill="#2a2f37" />
      <rect x={300} y={-90} width={10} height={60} fill="#3a404a" />
      <rect x={292} y={-34} width={26} height={6} fill="#3a404a" />
      <rect x={-4} y={-104} width={10} height={14} fill="#e04a2a" />
      {/* tractor */}
      <rect x={440} y={-96} width={200} height={28} fill="#16191f" />
      <rect x={452} y={-108} width={70} height={12} rx={3} fill="#3a404a" />
      <path d="M528 -96 V-252 Q528 -268 544 -268 H612 Q640 -268 646 -240 L660 -150 V-96 Z" fill="#20252c" />
      <path d="M538 -244 V-160 H600 V-244 Z" fill="#2c323b" />
      <path d="M604 -244 H624 Q634 -244 637 -234 L648 -168 H604 Z" fill="#a9c4d6" opacity={0.75} />
      <path d="M606 -240 H620 L626 -200 H606 Z" fill="#ffffff" opacity={0.35} />
      <rect x={646} y={-150} width={18} height={46} rx={3} fill="#3a404a" />
      <rect x={652} y={-142} width={10} height={4} fill="#ffd58a" />
      <rect x={536} y={-272} width={72} height={8} rx={3} fill="#3a404a" />
      <rect x={510} y={-230} width={8} height={130} fill="#3a404a" />
      <text x={545} y={-130} fontSize={12} fontFamily="var(--font-mono), monospace" fill="#8d929b" letterSpacing={2}>
        RL
      </text>
      {[...trailerWheels, ...tractorWheels].map((x) => (
        <g key={x} transform={`translate(${x} ${-TRUCK.wheelR})`}>
          <Wheel r={TRUCK.wheelR} part="truck-wheel" />
        </g>
      ))}
    </g>
  );
}
