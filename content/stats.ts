export type Stat = { value: number; decimals?: number; suffix: string; label: string; sub: string };

export const stats: Stat[] = [
  { value: 2500, suffix: "+", label: "Shipments per month", sub: "Across road, air and ocean lanes" }, // DUMMY
  { value: 98.2, decimals: 1, suffix: "%", label: "On-time delivery", sub: "Rolling 12-month average" }, // DUMMY
  { value: 8, suffix: "+", label: "Years in operation", sub: "Moving cargo since 2018" },
];
