"use client";

import { useState, type FormEvent } from "react";

const field = "w-full border-b border-paper/25 bg-transparent py-3 text-[15px] text-paper outline-none placeholder:text-paper/35 focus:border-brand";

// DUMMY indicative rates (₹ per kg) and transit days by mode — replace with real tariffs.
const MODES = {
  ftl: { label: "Road · FTL", perKg: 4.2, min: 18000, days: [2, 5] },
  ptl: { label: "Road · PTL", perKg: 7.5, min: 2500, days: [3, 7] },
  air: { label: "Air freight", perKg: 68, min: 4500, days: [1, 2] },
  ocean: { label: "Ocean · LCL", perKg: 11, min: 9000, days: [12, 28] },
} as const;

type Mode = keyof typeof MODES;

/** Mini quote form — gives an indicative range locally, nothing is sent. */
export function QuoteCalculator() {
  const [result, setResult] = useState<{ low: number; high: number; days: readonly number[]; label: string } | null>(null);

  const submit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const d = Object.fromEntries(new FormData(e.currentTarget)) as Record<string, string>;
    const kg = Math.max(1, Number(d.weight) || 0);
    const m = MODES[(d.mode as Mode) ?? "ftl"];
    const base = Math.max(m.min, kg * m.perKg);
    setResult({ low: Math.round(base * 0.9), high: Math.round(base * 1.15), days: m.days, label: m.label });
  };

  const inr = (n: number) => `₹${n.toLocaleString("en-IN")}`;

  return (
    <form onSubmit={submit} className="grid gap-6 sm:grid-cols-2">
      <label htmlFor="q-origin" className="flex flex-col">
        <span className="ui-label text-steel">Origin</span>
        <input id="q-origin" name="origin" required defaultValue="Delhi" className={field} />
      </label>
      <label htmlFor="q-dest" className="flex flex-col">
        <span className="ui-label text-steel">Destination</span>
        <input id="q-dest" name="destination" required placeholder="e.g. Bengaluru" className={field} />
      </label>
      <label htmlFor="q-weight" className="flex flex-col">
        <span className="ui-label text-steel">Weight (kg)</span>
        <input id="q-weight" name="weight" type="number" min={1} required placeholder="1200" className={field} />
      </label>
      <label htmlFor="q-mode" className="flex flex-col">
        <span className="ui-label text-steel">Type</span>
        <select id="q-mode" name="mode" className={field} defaultValue="ftl">
          {Object.entries(MODES).map(([k, m]) => (
            <option key={k} value={k} className="text-ink">
              {m.label}
            </option>
          ))}
        </select>
      </label>
      <div className="flex flex-wrap items-center gap-6 sm:col-span-2">
        <button type="submit" className="inline-flex h-12 items-center rounded-full bg-brand px-6 text-[14px] font-medium text-ink transition-colors hover:bg-paper">
          Estimate
        </button>
        {result && (
          <div role="status" className="flex flex-col">
            <span className="display text-[clamp(1.4rem,2.4vw,2rem)] leading-none">
              {inr(result.low)} – {inr(result.high)}
            </span>
            <span className="ui-label mt-2 text-paper/60">
              {result.label} · {result.days[0]}–{result.days[1]} days · indicative, excl. GST
            </span>
          </div>
        )}
      </div>
    </form>
  );
}
