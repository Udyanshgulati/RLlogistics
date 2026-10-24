/** Side-view wheel centred on (0,0). The inner hub group is what rotates. */
export function Wheel({ r, part }: { r: number; part?: string }) {
  return (
    <g>
      <circle r={r} fill="#16191f" />
      <circle r={r * 0.82} fill="#23272f" />
      <g data-part={part}>
        <circle r={r * 0.56} fill="#c9ccd2" />
        <circle r={r * 0.56} fill="none" stroke="#8d929b" strokeWidth={r * 0.06} />
        {[0, 60, 120, 180, 240, 300].map((d) => (
          <circle
            key={d}
            cx={Math.cos((d * Math.PI) / 180) * r * 0.36}
            cy={Math.sin((d * Math.PI) / 180) * r * 0.36}
            r={r * 0.06}
            fill="#5d636c"
          />
        ))}
        <rect x={-r * 0.05} y={-r * 0.5} width={r * 0.1} height={r * 0.22} fill="#8d929b" />
        <circle r={r * 0.16} fill="#5d636c" />
      </g>
    </g>
  );
}
