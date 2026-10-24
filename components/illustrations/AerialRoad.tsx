/** Stylised aerial view of a highway interchange — stands in for a photo (override: /assets/hero-road.jpg). */
export function AerialRoad({ className }: { className?: string }) {
  const roads = [
    "M-40 60 C 120 40, 180 180, 330 160 S 520 40, 680 90",
    "M-40 150 C 140 120, 220 260, 380 240 S 560 140, 680 190",
    "M120 -30 C 160 120, 60 220, 140 340",
    "M460 -30 C 420 120, 520 220, 470 340",
  ];
  return (
    <svg viewBox="0 0 640 320" className={className} aria-hidden preserveAspectRatio="xMidYMid slice">
      <rect width="640" height="320" fill="#3b4038" />
      <path d="M0 0 H640 V320 H0Z" fill="url(#ar-fields)" opacity="0.5" />
      <defs>
        <pattern id="ar-fields" width="80" height="80" patternUnits="userSpaceOnUse" patternTransform="rotate(18)">
          <rect width="80" height="80" fill="#495046" />
          <rect width="40" height="80" fill="#535a4f" />
        </pattern>
        <linearGradient id="ar-light" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#fff" stopOpacity="0.18" />
          <stop offset="1" stopColor="#000" stopOpacity="0.25" />
        </linearGradient>
      </defs>
      {roads.map((d, i) => (
        <g key={i}>
          <path d={d} stroke="#22252a" strokeWidth={i < 2 ? 46 : 30} fill="none" opacity="0.45" transform="translate(6 8)" />
          <path d={d} stroke="#9fa3a8" strokeWidth={i < 2 ? 42 : 26} fill="none" />
          <path d={d} stroke="#7d8187" strokeWidth={i < 2 ? 38 : 22} fill="none" />
          <path d={d} stroke="#f3f2ee" strokeWidth="1.5" strokeDasharray="10 12" fill="none" opacity="0.8" />
        </g>
      ))}
      {[
        [90, 66, 12],
        [250, 168, -8],
        [520, 70, 18],
        [330, 236, 4],
        [600, 186, 20],
      ].map(([x, y, r], i) => (
        <rect key={i} x={x} y={y} width="14" height="7" rx="2" fill={i % 2 ? "#ff5a1f" : "#f3f2ee"} transform={`rotate(${r} ${x} ${y})`} />
      ))}
      <rect width="640" height="320" fill="url(#ar-light)" />
    </svg>
  );
}
