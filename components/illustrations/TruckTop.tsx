/** Top-down truck heading down the screen (cab at the bottom). Local box: 0..60 × 0..200. */
export function TruckTop({ className, src }: { className?: string; src?: string }) {
  // eslint-disable-next-line @next/next/no-img-element
  if (src) return <img src={src} alt="" aria-hidden className={className} />;
  return (
    <svg viewBox="0 0 60 200" className={className} aria-hidden>
      <rect x="2" y="4" width="56" height="196" rx="6" fill="#0a0d14" opacity="0.18" transform="translate(3 3)" />
      {/* trailer with container */}
      <rect x="3" y="2" width="54" height="142" rx="2" fill="#e9ebee" />
      {Array.from({ length: 13 }, (_, i) => (
        <rect key={i} x="3" y={8 + i * 10.4} width="54" height="3" fill="#c9cdd3" />
      ))}
      <rect x="3" y="2" width="54" height="5" fill="#c3c8cf" />
      <rect x="3" y="139" width="54" height="5" fill="#c3c8cf" />
      <rect x="22" y="60" width="16" height="22" fill="#ff5a1f" opacity="0.9" />
      {/* coupling */}
      <rect x="24" y="144" width="12" height="10" fill="#3a404a" />
      {/* cab */}
      <path d="M6 154 H54 V188 Q54 198 44 198 H16 Q6 198 6 188 Z" fill="#20252c" />
      <path d="M10 178 H50 V188 Q50 194 44 194 H16 Q10 194 10 188 Z" fill="#a9c4d6" opacity="0.8" />
      <rect x="10" y="158" width="40" height="14" rx="2" fill="#2c323b" />
      <rect x="2" y="176" width="4" height="8" rx="1" fill="#3a404a" />
      <rect x="54" y="176" width="4" height="8" rx="1" fill="#3a404a" />
      <rect x="12" y="196" width="8" height="3" fill="#ffd58a" />
      <rect x="40" y="196" width="8" height="3" fill="#ffd58a" />
    </svg>
  );
}
