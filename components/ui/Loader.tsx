"use client";

import { useEffect, useRef, useState } from "react";
import { gsap, EXPO } from "@/lib/gsap";
import { useLoader } from "@/lib/loader";
import { company } from "@/content/company";

const MIN_MS = 1100;
const MAX_MS = 7000;

/** Intro: percentage counter → ink panel lifts → orange panel wipes up → page. */
export function Loader() {
  const { pending, markDone } = useLoader();
  const root = useRef<HTMLDivElement>(null);
  const [pct, setPct] = useState(0);
  const [gone, setGone] = useState(false);

  useEffect(() => {
    const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const progress = { v: 0 };
    let cancelled = false;

    // Creep toward 90% while work is pending; finish once everything resolves.
    const creep = gsap.to(progress, { v: 90, duration: 2.4, ease: "power2.out", onUpdate: () => setPct(Math.round(progress.v)) });

    const start = performance.now();
    const wait = new Promise((r) => requestAnimationFrame(r)).then(() =>
      Promise.race([
        Promise.all([document.fonts?.ready, ...pending()]).catch(() => undefined),
        new Promise((r) => setTimeout(r, MAX_MS)),
      ]),
    );

    wait
      .then(() => new Promise((r) => setTimeout(r, Math.max(0, MIN_MS - (performance.now() - start)))))
      .then(() => {
        if (cancelled) return;
        creep.kill();
        if (reduced) {
          setPct(100);
          markDone();
          setGone(true);
          return;
        }
        const el = root.current!;
        gsap
          .timeline({ onComplete: () => setGone(true) })
          .to(progress, { v: 100, duration: 0.45, ease: "power2.inOut", onUpdate: () => setPct(Math.round(progress.v)) })
          .to(el.querySelector("[data-loader-content]"), { yPercent: -40, opacity: 0, duration: 0.6, ease: "power2.in" })
          .to(el.querySelector("[data-loader-ink]"), { yPercent: -100, duration: 0.9, ease: EXPO }, "-=0.2")
          .to(el.querySelector("[data-loader-brand]"), { yPercent: -100, duration: 0.9, ease: EXPO }, "-=0.72")
          .add(markDone, "-=0.75");
      });

    return () => {
      cancelled = true;
      creep.kill();
    };
  }, [pending, markDone]);

  if (gone) return null;

  return (
    <div ref={root} className="fixed inset-0 z-[100]" role="status" aria-live="polite" aria-label={`Loading ${pct}%`}>
      <div data-loader-brand className="absolute inset-0 bg-brand" />
      <div data-loader-ink className="absolute inset-0 bg-ink text-paper">
        <div data-loader-content className="flex h-full flex-col justify-between p-gutter">
          <div className="flex items-start justify-between">
            <span className="ui-label text-steel">{company.name} {company.nameSub}</span>
            <span className="ui-label text-steel">Delhi · 28.61° N</span>
          </div>
          <div className="flex items-end justify-between gap-6">
            <span className="ui-label max-w-[18ch] text-steel">Preparing the route</span>
            <span className="display text-[clamp(4rem,14vw,12rem)] leading-[0.85] tabular-nums">
              {pct}
              <span className="text-brand">%</span>
            </span>
          </div>
          <div className="relative mt-6 h-px w-full bg-paper/15">
            <div className="absolute inset-y-0 left-0 bg-brand" style={{ width: `${pct}%` }} />
          </div>
        </div>
      </div>
    </div>
  );
}
