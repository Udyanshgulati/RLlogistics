"use client";

import { useEffect, useRef, useState } from "react";
import { ScrollTrigger } from "@/lib/gsap";
import { cn } from "@/lib/cn";

/** Scene → waypoint code on the route (Delhi hub out to the port and back to your door). */
const STOPS = [
  { scene: "hero", code: "DEL" },
  { scene: "about", code: "RL" },
  { scene: "yard", code: "ICD" },
  { scene: "road", code: "NH-48" },
  { scene: "ocean", code: "JNPT" },
  { scene: "trust", code: "CLIENTS" },
  { scene: "cta", code: "YOU" },
];

/**
 * Desktop journey tracker in the left margin: a dashed route that fills amber with scroll, with a
 * waypoint per scene that lights up once you've passed it. Positions come from the real scroll
 * positions of the scenes (pins included), re-measured on every ScrollTrigger refresh.
 */
export function JourneyRoute() {
  const fill = useRef<HTMLSpanElement>(null);
  const [stops, setStops] = useState<{ code: string; at: number }[]>([]);
  const [progress, setProgress] = useState(0);

  useEffect(() => {
    const measure = () => {
      const max = ScrollTrigger.maxScroll(window) || 1;
      setStops(
        STOPS.flatMap((s) => {
          const el = document.querySelector<HTMLElement>(`[data-scene="${s.scene}"]`);
          if (!el) return [];
          // Pinned scenes sit inside a pin-spacer; its top is where the scene starts in the scroll.
          const box = (el.parentElement?.classList.contains("pin-spacer") ? el.parentElement : el)!;
          const top = box.getBoundingClientRect().top + window.scrollY;
          return [{ code: s.code, at: Math.min(1, Math.max(0, top / max)) }];
        }),
      );
    };
    const st = ScrollTrigger.create({
      start: 0,
      end: "max",
      onUpdate: (self) => {
        if (fill.current) fill.current.style.transform = `scaleY(${self.progress})`;
        setProgress(self.progress);
      },
      onRefresh: measure,
    });
    measure();
    return () => st.kill();
  }, []);

  return (
    <div aria-hidden className="pointer-events-none fixed bottom-12 left-[calc(var(--gutter)+22px)] top-[128px] z-40 hidden w-16 lg:block">
      {/* route: dashed base + amber fill */}
      <span className="absolute inset-y-0 left-0 w-[2px] bg-[repeating-linear-gradient(to_bottom,rgb(var(--steel)/0.55)_0_8px,transparent_8px_14px)]" />
      <span ref={fill} className="absolute inset-y-0 left-0 w-[2px] origin-top scale-y-0 bg-brand" />
      {stops.map((s) => {
        const passed = progress >= s.at - 0.002;
        return (
          <span key={s.code} className="absolute left-0 flex -translate-y-1/2 items-center gap-2" style={{ top: `${s.at * 100}%` }}>
            <span
              className={cn(
                "-ml-[5px] block h-3 w-3 rounded-full border-2 transition-colors duration-300",
                passed ? "border-brand bg-brand" : "border-steel/70 bg-transparent",
              )}
            />
            <span className={cn("font-mono text-[10px] tracking-[0.12em] transition-colors duration-300", passed ? "text-brand" : "text-steel")}>{s.code}</span>
          </span>
        );
      })}
    </div>
  );
}
