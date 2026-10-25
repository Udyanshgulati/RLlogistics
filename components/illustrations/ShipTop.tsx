const COLORS = ["#d8343a", "#2f9e4f", "#1f4fb4", "#e8467c", "#f2f2f0", "#f08a24", "#2a8fd6", "#d8343a", "#f2f2f0", "#1f4fb4"];

/** Top-down container ship, bow up. viewBox 0..240 × 0..1000. */
export function ShipTop({ className, src }: { className?: string; src?: string }) {
  // eslint-disable-next-line @next/next/no-img-element
  if (src) return <img src={src} alt="" aria-hidden className={className} />;
  const bays = 11;
  const cols = 6;
  return (
    <svg viewBox="0 0 240 1000" className={className} aria-hidden>
      <defs>
        <linearGradient id="hull" x1="0" x2="1">
          <stop offset="0" stopColor="#1a2230" />
          <stop offset="0.5" stopColor="#2a3446" />
          <stop offset="1" stopColor="#141b26" />
        </linearGradient>
      </defs>
      <path d="M120 0 C 200 60, 236 170, 236 260 V 940 Q 236 1000 176 1000 H 64 Q 4 1000 4 940 V 260 C 4 170, 40 60, 120 0 Z" fill="url(#hull)" />
      <path d="M120 14 C 190 70, 222 172, 222 262 V 936 Q 222 986 172 986 H 68 Q 18 986 18 936 V 262 C 18 172, 50 70, 120 14 Z" fill="#3b4656" />
      {/* forecastle */}
      <path d="M120 30 C 170 76, 196 140, 200 196 H 40 C 44 140, 70 76, 120 30 Z" fill="#c9ced6" />
      <circle cx="120" cy="120" r="10" fill="#8d939c" />
      {/* container bays */}
      {Array.from({ length: bays }, (_, b) => (
        <g key={b} transform={`translate(26 ${214 + b * 58})`}>
          <rect width="188" height="54" fill="#2a3240" />
          {Array.from({ length: cols }, (_, c) => (
            <g key={c}>
              <rect x={2 + c * 31} y="2" width="29" height="50" fill={COLORS[(b * 3 + c * 7) % COLORS.length]} />
              {[10, 20, 30, 40].map((y) => (
                <rect key={y} x={2 + c * 31} y={y} width="29" height="1.5" fill="#000" opacity="0.18" />
              ))}
            </g>
          ))}
        </g>
      ))}
      {/* bridge + funnel near stern */}
      <rect x="22" y="866" width="196" height="64" rx="4" fill="#eef0f3" />
      <rect x="30" y="874" width="180" height="8" fill="#1d2430" opacity="0.7" />
      <rect x="96" y="896" width="48" height="26" rx="3" fill="#f2a900" />
      <rect x="104" y="900" width="32" height="6" fill="#16191f" />
      <rect x="40" y="944" width="160" height="30" rx="4" fill="#c9ced6" />
    </svg>
  );
}
