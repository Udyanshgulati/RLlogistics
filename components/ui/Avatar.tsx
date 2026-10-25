/**
 * Illustrated profile avatar (flat vector bust), used for testimonials instead of photos of real people.
 * Each person gets a fixed look from `look`; the square scales to whatever box it is put in.
 */
export type AvatarLook = {
  bg: string;
  skin: string;
  hair: string;
  shirt: string;
  /** "short" | "long" | "bun" | "side" */
  style: "short" | "long" | "bun" | "side";
  beard?: boolean;
  glasses?: boolean;
};

export function Avatar({ look, className }: { look: AvatarLook; className?: string }) {
  const { bg, skin, hair, shirt, style, beard, glasses } = look;
  return (
    <svg viewBox="0 0 100 100" className={className} aria-hidden>
      <rect width="100" height="100" fill={bg} />
      {/* soft light from top-left */}
      <rect width="100" height="100" fill="url(#av-light)" />
      <defs>
        <linearGradient id="av-light" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#fff" stopOpacity="0.18" />
          <stop offset="1" stopColor="#000" stopOpacity="0.08" />
        </linearGradient>
      </defs>
      {/* long hair falls behind the shoulders */}
      {style === "long" && <path d="M27 46 Q26 22 50 20 Q74 22 73 46 L76 82 H24 Z" fill={hair} />}
      {/* shoulders + collar */}
      <path d="M14 100 Q16 76 38 71 H62 Q84 76 86 100 Z" fill={shirt} />
      <path d="M42 71 L50 82 L58 71 Z" fill="#fff" opacity="0.85" />
      {/* neck */}
      <path d="M43 60 H57 V73 Q50 78 43 73 Z" fill={skin} />
      <path d="M43 66 Q50 70 57 66 V70 Q50 74 43 70 Z" fill="#000" opacity="0.08" />
      {/* face */}
      <ellipse cx="50" cy="46" rx="16" ry="19" fill={skin} />
      <ellipse cx="34.5" cy="48" rx="2.6" ry="4" fill={skin} />
      <ellipse cx="65.5" cy="48" rx="2.6" ry="4" fill={skin} />
      {beard && <path d="M35 50 Q36 66 50 67 Q64 66 65 50 Q62 60 50 60 Q38 60 35 50 Z" fill={hair} />}
      {/* hair */}
      {style === "short" && <path d="M33 44 Q31 25 50 25 Q69 25 67 44 Q64 33 50 33 Q38 33 33 44 Z" fill={hair} />}
      {style === "side" && <path d="M33 45 Q30 24 52 24 Q70 26 67 42 Q60 31 44 34 Q37 36 33 45 Z" fill={hair} />}
      {style === "long" && <path d="M33 46 Q32 26 50 26 Q68 26 67 46 Q62 32 46 32 Q38 34 33 46 Z" fill={hair} />}
      {style === "bun" && (
        <>
          <circle cx="50" cy="21" r="7" fill={hair} />
          <path d="M33 44 Q32 26 50 26 Q68 26 67 44 Q63 32 50 32 Q37 32 33 44 Z" fill={hair} />
        </>
      )}
      {/* features */}
      <ellipse cx="44" cy="47" rx="1.4" ry="1.6" fill="#1d1f24" />
      <ellipse cx="56" cy="47" rx="1.4" ry="1.6" fill="#1d1f24" />
      <path d="M41 42.5 Q44 41 47 42.5 M53 42.5 Q56 41 59 42.5" stroke={hair} strokeWidth="1.3" fill="none" strokeLinecap="round" />
      <path d="M50 48 Q49 53 51 54" stroke="#000" strokeOpacity="0.18" strokeWidth="1.1" fill="none" strokeLinecap="round" />
      <path d="M45.5 57.5 Q50 60.5 54.5 57.5" stroke="#7a3b2e" strokeOpacity="0.7" strokeWidth="1.3" fill="none" strokeLinecap="round" />
      {glasses && (
        <g stroke="#1d1f24" strokeWidth="1.1" fill="none">
          <rect x="38.5" y="43.5" width="10" height="7" rx="2.5" />
          <rect x="51.5" y="43.5" width="10" height="7" rx="2.5" />
          <path d="M48.5 46.5 H51.5" />
        </g>
      )}
    </svg>
  );
}
