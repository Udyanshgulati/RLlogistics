/**
 * Side-view wheel centred on (0,0). Only the rim group (data-part) rotates; the tyre keeps its
 * slightly flattened contact patch at the bottom. `dual` adds the inner tyre of a twin set peeking behind.
 * Uses gradients from <ShadingDefs />.
 */
export function Wheel({ r, part, dual = false }: { r: number; part?: string; dual?: boolean }) {
  const lugs = r > 22 ? 8 : 6;
  return (
    <g>
      {dual && (
        <g transform={`translate(${-r * 0.16} ${-r * 0.04})`}>
          <ellipse rx={r} ry={r * 0.985} cy={r * 0.015} fill="#141414" />
          <circle r={r * 0.6} fill="#3b3f45" />
        </g>
      )}
      {/* tyre: a touch wider than tall at the bottom where it carries the load */}
      <ellipse rx={r * 1.015} ry={r * 0.985} cy={r * 0.015} fill="url(#sh-tyre)" />
      <circle r={r * 0.92} fill="none" stroke="#333" strokeWidth={r * 0.05} />
      <circle r={r * 0.8} fill="none" stroke="#111" strokeWidth={r * 0.04} opacity={0.6} />
      <g data-part={part}>
        <circle r={r * 0.64} fill="url(#sh-rim)" />
        <circle r={r * 0.64} fill="none" stroke="#6b727b" strokeWidth={r * 0.04} />
        <circle r={r * 0.5} fill="none" stroke="#8a9199" strokeWidth={r * 0.03} />
        {Array.from({ length: lugs }, (_, k) => {
          const a = (k / lugs) * Math.PI * 2;
          return <circle key={k} cx={Math.cos(a) * r * 0.36} cy={Math.sin(a) * r * 0.36} r={r * 0.055} fill="#4b5158" />;
        })}
        <circle r={r * 0.2} fill="url(#sh-chrome)" />
        <rect x={-r * 0.03} y={-r * 0.6} width={r * 0.06} height={r * 0.16} fill="#5b626a" />
      </g>
      {/* rim highlight from the top-left light */}
      <path d={`M ${-r * 0.55} ${-r * 0.2} A ${r * 0.6} ${r * 0.6} 0 0 1 ${r * 0.1} ${-r * 0.58}`} stroke="#fff" strokeOpacity={0.35} strokeWidth={r * 0.05} fill="none" />
    </g>
  );
}
