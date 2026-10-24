"use client";

import { useState, type FormEvent } from "react";

// DUMMY tracking timeline for the demo.
const STEPS = ["Picked up · Okhla Phase II", "Departed Delhi hub", "In transit · NH-48, Jaipur bypass", "Arriving at destination hub"];

export function TrackShipment() {
  const [id, setId] = useState<string | null>(null);
  const submit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const v = String(new FormData(e.currentTarget).get("awb") ?? "").trim();
    setId(v || null);
  };
  return (
    <div>
      <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row sm:items-end">
        <label htmlFor="t-awb" className="flex flex-1 flex-col">
          <span className="ui-label text-steel">LR / AWB number</span>
          <input id="t-awb" name="awb" placeholder="RL2026-104233" className="w-full border-b border-ink/20 bg-transparent py-3 text-[15px] outline-none focus:border-brand" />
        </label>
        <button type="submit" className="inline-flex h-11 items-center justify-center rounded-full border border-ink/25 px-5 text-[13px] hover:border-brand hover:text-brand">
          Track
        </button>
      </form>
      {id && (
        <div role="status" className="mt-6">
          <p className="ui-label text-steel">
            Sample status for <span className="text-ink">{id}</span> · live tracking connects here
          </p>
          <ol className="mt-4 border-l border-ink/15">
            {STEPS.map((s, i) => (
              <li key={s} className="relative pb-4 pl-6 text-[14px]">
                <span aria-hidden className={`absolute -left-[5px] top-1.5 h-[9px] w-[9px] rounded-full ${i < 3 ? "bg-brand" : "border border-ink/30 bg-paper"}`} />
                <span className={i < 3 ? "text-ink" : "text-ink/45"}>{s}</span>
              </li>
            ))}
          </ol>
        </div>
      )}
    </div>
  );
}
