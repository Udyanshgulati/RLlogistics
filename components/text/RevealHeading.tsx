"use client";

import { useRef, type ElementType } from "react";
import { gsap, ScrollTrigger, SplitText, useGSAP, EXPO, MQ } from "@/lib/gsap";
import { cn } from "@/lib/cn";

export type TextMode =
  /** Plays once when it scrolls into view. */
  | "scroll"
  /** Plays when `play` becomes true (e.g. after the loader). */
  | "manual"
  /** Scrubbed against its own scroll position. */
  | "scrub"
  /** Does nothing by itself — a scene timeline calls buildRevealTl() on it. */
  | "external";

type Props = {
  lines: readonly string[];
  as?: ElementType;
  className?: string;
  /** Line indices rendered in --steel (e.g. the last line of a section heading). */
  mutedLines?: number[];
  mode?: TextMode;
  play?: boolean;
  delay?: number;
};

/**
 * Splits the heading into lines + chars; each line is masked and chars rise from yPercent 110.
 * SplitText keeps an aria-label with the full sentence and hides the fragments from AT.
 */
export function buildRevealTl(el: HTMLElement, opts: { stagger?: number; duration?: number } = {}) {
  const split = SplitText.create(el, { type: "lines,words,chars", mask: "lines", linesClass: "split-line" });
  const tl = gsap.timeline();
  tl.fromTo(
    split.chars,
    { yPercent: 110 },
    { yPercent: 0, duration: opts.duration ?? 1.1, ease: EXPO, stagger: opts.stagger ?? 0.02 },
  );
  return { tl, split };
}

export function RevealHeading({ lines, as: Tag = "h2", className, mutedLines = [], mode = "scroll", play, delay = 0 }: Props) {
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
        const { tl, split } = buildRevealTl(el);
        tl.delay(delay);
        if (mode === "scroll") {
          tl.pause();
          ScrollTrigger.create({ trigger: el, start: "top 85%", once: true, onEnter: () => tl.play() });
        } else if (mode === "scrub") {
          tl.pause();
          gsap.to(tl, {
            progress: 1,
            ease: "none",
            scrollTrigger: { trigger: el, start: "top 90%", end: "bottom 55%", scrub: 1 },
          });
        }
        return () => split.revert();
      });
      mm.add(MQ.reduced, () => gsap.set(el, { autoAlpha: 1 }));
    },
    { dependencies: [mode, play], scope: ref },
  );

  return (
    <Tag ref={ref} className={cn("display", className)} data-reveal>
      {lines.map((line, i) => (
        <span key={i} className={cn("block", mutedLines.includes(i) && "text-steel")}>
          {line}
        </span>
      ))}
    </Tag>
  );
}
