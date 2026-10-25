/**
 * Top-down container terminal (think JNPT / Nhava Sheva): a ship at the berth, three quay cranes,
 * the truck lane along the quay, the container yard with an RTG gantry, and the road out towards Delhi
 * drawn as an amber route line. viewBox 1600 × 1000. Animated parts carry data attributes:
 * [data-crane] (moves along the quay), [data-trolley] (runs ship ↔ quay), [data-truck] (lane traffic),
 * [data-rtg] (yard gantry), [data-route] (stroke drawn with pathLength 1).
 */
const PALETTE = [
  "#1554d1",
  "#0b1f4d",
  "#f2a900",
  "#e9edf3",
  "#128080",
  "#5b7fd6",
  "#c94f2c",
  "#2f3b52",
];

// Deterministic pseudo-random so the server and client render the same boxes.
function rand(seed: number) {
  let s = seed;
  return () => {
    s = (s * 16807) % 2147483647;
    return (s - 1) / 2147483646;
  };
}

function Stack({
  x,
  y,
  cols,
  rows,
  w,
  h,
  seed,
  gap = 3,
}: {
  x: number;
  y: number;
  cols: number;
  rows: number;
  w: number;
  h: number;
  seed: number;
  gap?: number;
}) {
  const r = rand(seed);
  const boxes = [];
  for (let i = 0; i < cols; i++)
    for (let j = 0; j < rows; j++) {
      if (r() < 0.08) continue; // a few empty slots read as a working yard
      const c = PALETTE[Math.floor(r() * PALETTE.length)];
      boxes.push(
        <g key={`${i}-${j}`}>
          <rect
            x={x + i * (w + gap)}
            y={y + j * (h + gap)}
            width={w}
            height={h}
            rx={1.5}
            fill={c}
          />
          <rect
            x={x + i * (w + gap)}
            y={y + j * (h + gap)}
            width={w}
            height={h * 0.28}
            fill="#fff"
            opacity={0.18}
          />
        </g>,
      );
    }
  return <g>{boxes}</g>;
}

export function PortTerminal({
  className,
  preserveAspectRatio,
}: {
  className?: string;
  preserveAspectRatio?: string;
}) {
  const cranesX = [470, 760, 1050];
  return (
    <svg
      viewBox="0 0 1600 1000"
      preserveAspectRatio={preserveAspectRatio}
      className={className}
      aria-hidden
    >
      <defs>
        <linearGradient id="pt-water" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#0b3aa8" />
          <stop offset="1" stopColor="#1554d1" />
        </linearGradient>
        <linearGradient id="pt-land" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#dde4ee" />
          <stop offset="1" stopColor="#cfd8e5" />
        </linearGradient>
      </defs>

      {/* Water + light ripples */}
      <rect width="1600" height="345" fill="url(#pt-water)" />
      {Array.from({ length: 40 }, (_, i) => (
        <path
          key={i}
          d={`M${(i * 97) % 1600} ${30 + ((i * 53) % 290)} q 14 -5 28 0`}
          stroke="#fff"
          strokeOpacity={0.14}
          strokeWidth={2}
          fill="none"
        />
      ))}

      {/* Land: quay apron, lane, yard */}
      <rect y="345" width="1600" height="655" fill="url(#pt-land)" />
      <rect y="338" width="1600" height="12" fill="#9aa7ba" />
      {Array.from({ length: 26 }, (_, i) => (
        <rect
          key={i}
          x={20 + i * 62}
          y={336}
          width={16}
          height={6}
          rx={2}
          fill="#f2a900"
        />
      ))}
      {/* crane rails */}
      <line
        x1="0"
        x2="1600"
        y1="372"
        y2="372"
        stroke="#7d8aa0"
        strokeWidth={3}
      />
      <line
        x1="0"
        x2="1600"
        y1="470"
        y2="470"
        stroke="#7d8aa0"
        strokeWidth={3}
      />
      {/* truck lane with markings */}
      <rect y="500" width="1600" height="74" fill="#2a3448" />
      {Array.from({ length: 30 }, (_, i) => (
        <rect
          key={i}
          x={i * 60}
          y={535}
          width={30}
          height={4}
          fill="#f4f7fb"
          opacity={0.75}
        />
      ))}

      {/* Ship at the berth: bow to the right, accommodation block aft */}
      <g>
        <path
          d="M200 125 H1250 Q1330 130 1370 222 Q1330 314 1250 319 H200 Q182 319 182 300 V144 Q182 125 200 125 Z"
          fill="#26324a"
        />
        <path
          d="M210 136 H1244 Q1316 141 1350 222 Q1316 303 1244 308 H210 Z"
          fill="#33415d"
        />
        <Stack
          x={340}
          y={146}
          cols={26}
          rows={7}
          w={32}
          h={20}
          seed={7}
          gap={4}
        />
        <rect x={214} y={150} width={104} height={144} rx={6} fill="#f4f7fb" />
        <rect x={226} y={164} width={80} height={12} rx={3} fill="#9fb3d4" />
        <rect x={226} y={186} width={80} height={12} rx={3} fill="#9fb3d4" />
        <rect x={256} y={250} width={20} height={34} rx={3} fill="#f2a900" />
        <text
          x={1210}
          y={300}
          fontSize={16}
          fontFamily="var(--font-mono), monospace"
          fill="#c9d4e6"
          letterSpacing={3}
        >
          RL EXPRESS
        </text>
      </g>

      {/* Quay cranes: legs on the rails, boom out over the ship, trolley that runs ship ↔ quay */}
      {cranesX.map((x, i) => (
        <g key={x} transform={`translate(${x} 0)`}>
          <g data-crane={i}>
            <rect
              x={-46}
              y={70}
              width={92}
              height={410}
              fill="none"
              stroke="#f4f7fb"
              strokeWidth={6}
            />
            <line
              x1={-46}
              y1={70}
              x2={46}
              y2={480}
              stroke="#f4f7fb"
              strokeWidth={3}
              opacity={0.6}
            />
            <line
              x1={46}
              y1={70}
              x2={-46}
              y2={480}
              stroke="#f4f7fb"
              strokeWidth={3}
              opacity={0.6}
            />
            <rect
              x={-60}
              y={360}
              width={120}
              height={22}
              rx={3}
              fill="#1554d1"
            />
            <rect
              x={-60}
              y={460}
              width={120}
              height={22}
              rx={3}
              fill="#1554d1"
            />
            <g transform="translate(0 190)">
              <g data-trolley>
                <rect
                  x={-30}
                  y={-16}
                  width={60}
                  height={32}
                  rx={4}
                  fill="#f2a900"
                />
                <rect
                  x={-20}
                  y={-10}
                  width={40}
                  height={20}
                  rx={2}
                  fill="#0b1f4d"
                  opacity={0.35}
                />
              </g>
            </g>
          </g>
        </g>
      ))}

      {/* Lane traffic: trucks pulling boxes from the cranes to the yard */}
      <g data-trucks>
        {[80, 380, 700, 1020, 1340].map((x, i) => (
          <g key={x} transform={`translate(${x} 537)`}>
            <g data-truck={i}>
              {/* top-down tractor + 40ft box, heading right */}
              <rect
                x={-118}
                y={-17}
                width={150}
                height={34}
                rx={2}
                fill={PALETTE[(i * 3) % PALETTE.length]}
              />
              <rect
                x={-118}
                y={-17}
                width={150}
                height={9}
                fill="#fff"
                opacity={0.2}
              />
              <rect
                x={38}
                y={-15}
                width={34}
                height={30}
                rx={5}
                fill="#f4f7fb"
              />
              <rect
                x={60}
                y={-12}
                width={9}
                height={24}
                rx={2}
                fill="#9fb3d4"
              />
            </g>
          </g>
        ))}
      </g>

      {/* Container yard blocks + RTG gantry */}
      <Stack x={60} y={620} cols={20} rows={6} w={30} h={16} seed={21} />
      <Stack x={760} y={620} cols={20} rows={6} w={30} h={16} seed={42} />
      <Stack x={60} y={790} cols={20} rows={6} w={30} h={16} seed={63} />
      <Stack x={760} y={790} cols={14} rows={6} w={30} h={16} seed={84} />
      <g transform="translate(980 0)">
        <g data-rtg>
          <rect x={-8} y={600} width={16} height={140} fill="#f2a900" />
          <rect x={60} y={600} width={16} height={140} fill="#f2a900" />
          <rect x={-14} y={596} width={96} height={10} rx={2} fill="#0b1f4d" />
          <rect x={-14} y={734} width={96} height={10} rx={2} fill="#0b1f4d" />
        </g>
      </g>
      <text
        x={240}
        y={608}
        fontSize={15}
        fontFamily="var(--font-mono), monospace"
        fill="#55627a"
        letterSpacing={3}
      >
        YARD B · EXPORT
      </text>
      <text
        x={240}
        y={492}
        fontSize={15}
        fontFamily="var(--font-mono), monospace"
        fill="#55627a"
        letterSpacing={3}
      >
        BERTH 3 · JNPT
      </text>

      {/* Gate road out to the right, and the amber route to Delhi */}
      <rect x={1320} y={574} width={70} height={426} fill="#2a3448" />
      <path
        data-route
        d="M1355 1000 V600 Q1355 560 1400 548 C 1480 528, 1540 470, 1620 420"
        pathLength={1}
        fill="none"
        stroke="#f2a900"
        strokeWidth={5}
        strokeDasharray="0.02 0.012"
        strokeLinecap="round"
      />
      <g transform="translate(1440 470)">
        <rect x={0} y={-22} width={150} height={36} rx={6} fill="#0b1f4d" />
        <text
          x={14}
          y={2}
          fontSize={15}
          fontFamily="var(--font-mono), monospace"
          fill="#f2a900"
          letterSpacing={2}
        >
          NH-48 → DEL
        </text>
      </g>
    </svg>
  );
}
