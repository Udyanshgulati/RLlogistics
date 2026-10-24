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
  /** Number of mis-registered copies behind the crisp text (2–3 reads best). */
  copies?: number;
};

// Offsets in em — a double-exposed print, mostly vertical drift with a little horizontal slip.
const OFFSETS = [
  { x: -0.06, y: 0.42 },
  { x: 0.08, y: -0.3 },
  { x: -0.03, y: 0.85 },
];

const SCRAMBLE_CHARS = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";

/**
 * Builds the "settling into register" timeline: ghost copies converge onto the main text and fade
 * while the main text's letters scramble into place. Works one-shot or scrubbed.
 */
export function buildGhostTl(el: HTMLElement, opts: { duration?: number } = {}) {
  const duration = opts.duration ?? 1.2;
  const fs = parseFloat(getComputedStyle(el).fontSize) || 16;
  const ghosts = gsap.utils.toArray<HTMLElement>(el.querySelectorAll("[data-ghost]"));
  const lines = gsap.utils.toArray<HTMLElement>(el.querySelectorAll("[data-main] [data-line]"));
  const tl = gsap.timeline();
  // Hidden until the effect starts, so a paused timeline at 0 shows nothing (not the offset copies).
  tl.fromTo(el, { autoAlpha: 0 }, { autoAlpha: 1, duration: 0.12, ease: "none" }, 0);

  ghosts.forEach((g, i) => {
    const o = OFFSETS[i % OFFSETS.length];
    tl.fromTo(
      g,
      { x: o.x * fs, y: o.y * fs, opacity: 0.4 - i * 0.08 },
      { x: 0, y: 0, opacity: 0, duration, ease: EXPO },
      i * 0.05,
    );
  });

  tl.fromTo(
    el.querySelector("[data-main]"),
    { opacity: 0, y: 0.12 * fs },
    { opacity: 1, y: 0, duration: duration * 0.7, ease: EXPO },
    0.05,
  );

  lines.forEach((line, i) => {
    tl.to(
      line,
      {
        duration: duration * 0.9,
        ease: "none",
        scrambleText: { text: line.dataset.text ?? "", chars: SCRAMBLE_CHARS, revealDelay: 0.25, speed: 0.7 },
      },
      0.05 + i * 0.08,
    );
  });

  const restore = () => lines.forEach((l) => (l.textContent = l.dataset.text ?? ""));
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
  copies = 3,
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

  const renderLines = (main: boolean) =>
    lines.map((line, i) => (
      <span
        key={i}
        className={cn("block whitespace-nowrap", mutedLines.includes(i) && "text-steel")}
        {...(main ? { "data-line": "", "data-text": line } : {})}
      >
        {line}
      </span>
    ));

  return (
    <Tag ref={ref} className={cn("display relative", className)} data-ghost-heading>
      <span className="sr-only">{lines.join(" ")}</span>
      {Array.from({ length: copies }, (_, i) => (
        <span key={i} aria-hidden data-ghost className="pointer-events-none absolute inset-0 opacity-0 will-change-transform">
          {renderLines(false)}
        </span>
      ))}
      <span aria-hidden data-main className="relative block">
        {renderLines(true)}
      </span>
    </Tag>
  );
}
