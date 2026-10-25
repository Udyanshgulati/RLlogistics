"use client";

import { useRef, type ElementType } from "react";
import { gsap, ScrollTrigger, useGSAP, EXPO, MQ } from "@/lib/gsap";
import { cn } from "@/lib/cn";
import type { TextMode } from "./RevealHeading";

type Props = {
  lines: readonly string[];
  as?: ElementType;
  className?: string;
  mutedLines?: number[];
  mode?: TextMode;
  play?: boolean;
  delay?: number;
  /** @deprecated kept for API compatibility; unused. */
  copies?: number;
};

/**
 * RL "lane" reveal: each line rises out of its own mask while an amber lane marking draws in beneath
 * the heading, like a road being painted ahead of the truck. Works one-shot or scrubbed.
 * (Name and API kept so the scenes don't change; the old ghost/scramble effect is gone.)
 */
export function buildGhostTl(el: HTMLElement, opts: { duration?: number } = {}) {
  const duration = opts.duration ?? 1.1;
  const lines = gsap.utils.toArray<HTMLElement>(el.querySelectorAll("[data-main] [data-line]"));
  const rule = el.querySelector("[data-rule]");
  const tl = gsap.timeline();
  tl.fromTo(el, { autoAlpha: 0 }, { autoAlpha: 1, duration: 0.05, ease: "none" }, 0);
  tl.fromTo(lines, { yPercent: 110 }, { yPercent: 0, duration, ease: EXPO, stagger: 0.09 }, 0);
  if (rule) tl.fromTo(rule, { scaleX: 0 }, { scaleX: 1, duration: duration * 0.9, ease: "power3.inOut" }, 0.15);
  const restore = () => {};
  return { tl, restore };
}

export function GhostHeading({
  lines,
  as: Tag = "h2",
  className,
  mutedLines = [],
  mode = "scroll",
  play,
  delay = 0,
}: Props) {
  const ref = useRef<HTMLElement>(null);

  useGSAP(
    () => {
      const el = ref.current;
      if (!el || mode === "external") return;
      if (mode === "manual" && !play) {
        gsap.set(el, { autoAlpha: 0 });
        return;
      }
      const mm = gsap.matchMedia();
      mm.add(MQ.motion, () => {
        gsap.set(el, { autoAlpha: 1 });
        const { tl, restore } = buildGhostTl(el);
        tl.delay(delay);
        if (mode === "scroll") {
          tl.pause();
          ScrollTrigger.create({ trigger: el, start: "top 85%", once: true, onEnter: () => tl.play() });
        } else if (mode === "scrub") {
          tl.pause();
          gsap.to(tl, {
            progress: 1,
            ease: "none",
            scrollTrigger: { trigger: el, start: "top 90%", end: "bottom 50%", scrub: 1 },
          });
        }
        return restore;
      });
      mm.add(MQ.reduced, () => gsap.set(el, { autoAlpha: 1 }));
    },
    { dependencies: [mode, play], scope: ref },
  );

  return (
    <Tag ref={ref} className={cn("display relative", className)} data-ghost-heading>
      <span className="sr-only">{lines.join(" ")}</span>
      <span aria-hidden data-main className="relative block">
        {lines.map((line, i) => (
          <span key={i} className="block overflow-hidden pb-[0.06em]">
            <span data-line className={cn("block whitespace-nowrap", mutedLines.includes(i) && "text-steel")}>
              {line}
            </span>
          </span>
        ))}
      </span>
      {/* Lane marking: amber dashes that draw in under the heading. */}
      <span
        aria-hidden
        data-rule
        className="mt-[0.28em] block h-[3px] w-[min(3.2em,180px)] origin-left bg-[repeating-linear-gradient(90deg,rgb(var(--brand))_0_18px,transparent_18px_28px)]"
      />
    </Tag>
  );
}
