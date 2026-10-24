type Props = { w: number; h: number; color: string; rib: string; label?: string; x?: number; y?: number; part?: string; img?: string };

/** Side-view ISO container: corrugated panel, top/bottom rails, corner castings and door end. */
export function Container({ w, h, color, rib, label, x = 0, y = 0, part, img }: Props) {
  const ribs = Math.round(w / 14);
  if (img)
    return (
      <g data-part={part} transform={`translate(${x} ${y})`}>
        <image href={img} width={w} height={h} preserveAspectRatio="none" />
      </g>
    );
  return (
    <g data-part={part} transform={`translate(${x} ${y})`}>
      <rect width={w} height={h} fill={color} />
      {Array.from({ length: ribs }, (_, i) => (
        <rect key={i} x={10 + (i * (w - 20)) / ribs} y={10} width={(w - 20) / ribs / 2.2} height={h - 20} fill={rib} opacity={0.55} />
      ))}
      <rect width={w} height={9} fill={rib} />
      <rect y={h - 9} width={w} height={9} fill={rib} />
      <rect width={10} height={h} fill={rib} />
      <rect x={w - 10} width={10} height={h} fill={rib} />
      {[
        [0, 0],
        [w - 14, 0],
        [0, h - 12],
        [w - 14, h - 12],
      ].map(([cx, cy], i) => (
        <rect key={i} x={cx} y={cy} width={14} height={12} fill="#2b2f36" opacity={0.55} />
      ))}
      {label && (
        <text x={w - 28} y={30} textAnchor="end" fontSize={11} fontFamily="monospace" fill={rib} opacity={0.9}>
          {label}
        </text>
      )}
      <rect width={w} height={h} fill="url(#ctr-shade)" />
    </g>
  );
}
