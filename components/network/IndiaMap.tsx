"use client";

import { useMemo, useRef, useState } from "react";
import { gsap, useGSAP, MQ } from "@/lib/gsap";
import { hubs, type Hub } from "@/content/network";
import points from "@/content/india-points.json";
import { cn } from "@/lib/cn";

// Equirectangular with a cos(lat) squeeze — reads like the globe, flattened.
const BOUNDS = { lng0: 67, lng1: 98, lat0: 6, lat1: 37.5 };
const K = Math.cos((22 * Math.PI) / 180);
const W = (BOUNDS.lng1 - BOUNDS.lng0) * K * 30;
const H = (BOUNDS.lat1 - BOUNDS.lat0) * 30;
const project = (lng: number, lat: number) => [(lng - BOUNDS.lng0) * K * 30, (BOUNDS.lat1 - lat) * 30] as const;

const KIND_LABEL: Record<Hub["kind"], string> = { hub: "Head office", port: "Port gateway", icd: "Inland container depot", city: "Partner hub" };

/** Interactive flat India map: dotted landmass, hubs, animated routes out of Delhi. */
export function IndiaMap() {
  const root = useRef<HTMLDivElement>(null);
  const [active, setActive] = useState<Hub>(hubs[0]);
  const delhi = project(hubs[0].lng, hubs[0].lat);

  const routes = useMemo(
    () =>
      hubs.slice(1).map((h) => {
        const [x, y] = project(h.lng, h.lat);
        const mx = (delhi[0] + x) / 2;
        const my = (delhi[1] + y) / 2 - Math.hypot(x - delhi[0], y - delhi[1]) * 0.18;
        return { id: h.id, d: `M${delhi[0]} ${delhi[1]} Q${mx} ${my} ${x} ${y}` };
      }),
    [delhi],
  );

  useGSAP(
    () => {
      const mm = gsap.matchMedia();
      mm.add(MQ.motion, () => {
        const tl = gsap.timeline({ scrollTrigger: { trigger: root.current, start: "top 75%" } });
        tl.fromTo("[data-dot]", { opacity: 0 }, { opacity: 1, duration: 0.6, stagger: { amount: 1.2, from: "random" } })
          .fromTo("[data-route]", { strokeDashoffset: 1 }, { strokeDashoffset: 0, duration: 1.2, stagger: 0.06, ease: "power2.out" }, 0.6)
          .fromTo("[data-hub]", { scale: 0, transformOrigin: "center" }, { scale: 1, duration: 0.5, stagger: 0.05, ease: "back.out(2)" }, 0.8);
      });
    },
    { scope: root },
  );

  return (
    <div ref={root} className="grid gap-10 lg:grid-cols-[1.4fr_1fr]">
      <div className="relative">
        <svg viewBox={`-20 -20 ${W + 40} ${H + 40}`} className="h-auto w-full" role="img" aria-label="Map of the RL Logistics network across India">
          <g aria-hidden>
            {(points as [number, number][]).map(([lng, lat], i) => {
              const [x, y] = project(lng, lat);
              return <circle key={i} data-dot cx={x} cy={y} r={3.4} fill="rgb(var(--paper) / 0.32)" />;
            })}
          </g>
          <g aria-hidden fill="none">
            {routes.map((r) => (
              <path
                key={r.id}
                data-route
                d={r.d}
                pathLength={1}
                strokeDasharray="1"
                stroke={active.id === r.id ? "rgb(var(--brand))" : "rgb(var(--brand) / 0.45)"}
                strokeWidth={active.id === r.id ? 2.5 : 1.4}
                className="transition-[stroke,stroke-width] duration-300"
              />
            ))}
          </g>
          {hubs.map((h) => {
            const [x, y] = project(h.lng, h.lat);
            const on = active.id === h.id;
            return (
              <g key={h.id} transform={`translate(${x} ${y})`}>
                <g data-hub>
                  {h.kind === "hub" && <circle r={18} fill="rgb(var(--brand) / 0.25)" className="animate-ping [transform-box:fill-box] [transform-origin:center]" />}
                  <circle
                    r={on ? 9 : 6.5}
                    fill={h.kind === "hub" || on ? "rgb(var(--brand))" : "rgb(var(--paper))"}
                    stroke="rgb(var(--ink))"
                    strokeWidth={2}
                    className="cursor-pointer transition-all duration-300"
                    tabIndex={0}
                    role="button"
                    aria-label={`${h.name}: ${h.detail}`}
                    onMouseEnter={() => setActive(h)}
                    onFocus={() => setActive(h)}
                    onClick={() => setActive(h)}
                  />
                </g>
                {(on || h.kind !== "city") && (
                  <text x={12} y={4} fontSize={13} fontFamily="var(--font-mono), monospace" letterSpacing={1} fill={on ? "rgb(var(--brand))" : "rgb(var(--paper) / 0.75)"} className="pointer-events-none uppercase">
                    {h.name}
                  </text>
                )}
              </g>
            );
          })}
        </svg>
      </div>

      <div className="flex flex-col gap-8">
        <div className="border-t border-paper/15 pt-6" aria-live="polite">
          <p className="ui-label text-brand">{KIND_LABEL[active.kind]}</p>
          <p className="display mt-3 text-[clamp(1.8rem,3vw,2.8rem)] leading-none">{active.name}</p>
          <p className="mt-3 text-[15px] text-paper/70">{active.detail}</p>
          <p className="ui-label mt-3 text-steel">
            {active.lat.toFixed(2)}° N · {active.lng.toFixed(2)}° E
          </p>
        </div>
        <ul className="grid grid-cols-2 gap-x-6 gap-y-1 text-[14px]">
          {hubs.map((h) => (
            <li key={h.id}>
              <button
                type="button"
                onClick={() => setActive(h)}
                onMouseEnter={() => setActive(h)}
                className={cn("w-full py-1.5 text-left transition-colors", active.id === h.id ? "text-brand" : "text-paper/65 hover:text-paper")}
              >
                {h.name}
              </button>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}
