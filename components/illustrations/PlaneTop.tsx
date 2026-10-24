/** Top-down cargo plane, nose pointing right. viewBox 0..1000 × 0..800. */
export function PlaneTop({ className, src }: { className?: string; src?: string }) {
  // eslint-disable-next-line @next/next/no-img-element
  if (src) return <img src={src} alt="" aria-hidden className={className} />;
  return (
    <svg viewBox="0 0 1000 800" className={className} aria-hidden>
      <defs>
        <linearGradient id="fus" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#ffffff" />
          <stop offset="0.55" stopColor="#eef1f5" />
          <stop offset="1" stopColor="#c9d0da" />
        </linearGradient>
        <linearGradient id="wing" x1="0" y1="0" x2="1" y2="0">
          <stop offset="0" stopColor="#dfe4eb" />
          <stop offset="1" stopColor="#f7f9fb" />
        </linearGradient>
      </defs>
      <g opacity="0.16" transform="translate(40 60)">
        <path d="M60 380 L900 360 Q990 380 900 420 L60 420 Z" fill="#0a0d14" />
        <path d="M560 380 L330 40 H260 L380 380 Z M560 420 L330 760 H260 L380 420 Z" fill="#0a0d14" />
      </g>
      {/* wings */}
      <path d="M560 372 L330 30 H262 Q252 30 256 42 L370 372 Z" fill="url(#wing)" />
      <path d="M560 428 L330 770 H262 Q252 770 256 758 L370 428 Z" fill="url(#wing)" />
      {/* engines */}
      {[[420, 210], [380, 120], [420, 590], [380, 680]].map(([x, y], i) => (
        <g key={i}>
          <rect x={x - 70} y={y - 16} width="110" height="32" rx="16" fill="#d5dbe3" />
          <rect x={x + 26} y={y - 16} width="14" height="32" rx="6" fill="#9aa3ae" />
        </g>
      ))}
      {/* tailplane */}
      <path d="M150 384 L60 250 H22 L70 384 Z M150 416 L60 550 H22 L70 416 Z" fill="url(#wing)" />
      <path d="M40 392 H170 V408 H40 Z" fill="#ff5a1f" />
      {/* fuselage */}
      <path d="M30 372 H860 Q980 372 994 400 Q980 428 860 428 H30 Q14 428 14 400 Q14 372 30 372 Z" fill="url(#fus)" />
      <path d="M920 384 Q960 388 972 400 Q960 412 920 416 Z" fill="#26303c" />
      <rect x="300" y="396" width="420" height="3" fill="#b9c1cc" />
      <text x="520" y="392" fontSize="14" fontFamily="monospace" fill="#8a929d" letterSpacing="4">RL AIR CARGO</text>
    </svg>
  );
}
