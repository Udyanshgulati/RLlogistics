"use client";

import { useRef } from "react";
import { gsap, ScrollTrigger, useGSAP, EXPO, MQ } from "@/lib/gsap";
import { cn } from "@/lib/cn";

type Props = {
  value: number;
  decimals?: number;
  suffix?: string;
  className?: string;
  mode?: "scroll" | "external";
};

const format = (value: number, decimals = 0) =>
  value.toLocaleString("en-US", { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

/** Odometer roll + vertical blur settle. */
export function buildCounterTl(el: HTMLElement) {
  const strips = gsap.utils.toArray<HTMLElement>(el.querySelectorAll("[data-strip]"));
  const visual = el.querySelector("[data-counter-visual]");
  const tl = gsap.timeline();
  tl.fromTo(visual, { filter: "blur(8px)", opacity: 0, y: 24 }, { filter: "blur(0px)", opacity: 1, y: 0, duration: 1.1, ease: EXPO }, 0);
  strips.forEach((strip, i) => {
    const len = Number(strip.dataset.len);
    tl.fromTo(
      strip,
      { yPercent: 0 },
      { yPercent: (-100 * (len - 1)) / len, duration: 1.6 + i * 0.08, ease: "power3.out" },
      0,
    );
  });
  return tl;
}

export function Counter({ value, decimals = 0, suffix = "", className, mode = "scroll" }: Props) {
  const ref = useRef<HTMLSpanElement>(null);
  const text = format(value, decimals);
  const digitCount = text.replace(/\D/g, "").length;

  useGSAP(
    () => {
      const el = ref.current;
      if (!el || mode === "external") return;
      const mm = gsap.matchMedia();
      mm.add(MQ.motion, () => {
        const tl = buildCounterTl(el).pause();
        ScrollTrigger.create({ trigger: el, start: "top 85%", once: true, onEnter: () => tl.play() });
      });
    },
    { scope: ref },
  );

  let digitIndex = 0;
  return (
    <span ref={ref} className={cn("display inline-block leading-none", className)} data-counter>
      <span className="sr-only">
        {text}
        {suffix}
      </span>
      <span aria-hidden data-counter-visual className="inline-flex leading-none">
        {[...text].map((ch, i) => {
          if (!/\d/.test(ch)) return <span key={i}>{ch}</span>;
          // Rightmost digits spin more, like a real odometer.
          const cycles = 1 + (digitCount - 1 - digitIndex++);
          const seq = [...Array.from({ length: cycles * 10 }, (_, k) => k % 10), ...Array.from({ length: Number(ch) + 1 }, (_, k) => k)];
          return (
            <span key={i} className="relative inline-block h-[1em] overflow-hidden">
              <span data-strip data-len={seq.length} className="flex flex-col">
                {seq.map((d, k) => (
                  <span key={k} className="block h-[1em] leading-none">
                    {d}
                  </span>
                ))}
              </span>
            </span>
          );
        })}
        <span className="text-brand">{suffix}</span>
      </span>
    </span>
  );
}
