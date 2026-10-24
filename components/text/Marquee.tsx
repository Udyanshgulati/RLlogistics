"use client";

import { useRef } from "react";
import { gsap, useGSAP, MQ } from "@/lib/gsap";
import { cn } from "@/lib/cn";

type Props = {
  text: string;
  className?: string;
  /** Fraction of scroll distance the text travels horizontally. */
  speed?: number;
  /** Element whose scroll range drives the motion (defaults to the marquee itself). */
  trigger?: string | HTMLElement | null;
  start?: string;
  end?: string;
  outline?: boolean;
  /** "external": a scene timeline moves [data-track] itself (needed inside pinned stages). */
  drive?: "self" | "external";
};

/** Giant faint watermark text that drifts sideways with scroll. Purely decorative. */
export function Marquee({
  text,
  className,
  speed = 0.3,
  trigger,
  start = "top bottom",
  end = "bottom top",
  outline = true,
  drive = "self",
}: Props) {
  const ref = useRef<HTMLDivElement>(null);

  useGSAP(
    () => {
      const el = ref.current;
      if (!el || drive === "external") return;
      const track = el.querySelector("[data-track]");
      const mm = gsap.matchMedia();
      mm.add(MQ.motion, () => {
        gsap.fromTo(
          track,
          { x: 0 },
          {
            x: () => -speed * (window.innerHeight + el.offsetHeight) * 1.5,
            ease: "none",
            scrollTrigger: { trigger: trigger ?? el, start, end, scrub: true, invalidateOnRefresh: true },
          },
        );
      });
    },
    { scope: ref, dependencies: [trigger] },
  );

  return (
    <div ref={ref} aria-hidden className={cn("pointer-events-none select-none overflow-hidden", className)}>
      <div data-track className="flex w-max whitespace-nowrap will-change-transform">
        {Array.from({ length: 4 }, (_, i) => (
          <span key={i} className={cn("display pr-[0.4em] text-[clamp(5rem,18vw,18rem)] leading-none", outline && "text-outline")}>
            {text}
          </span>
        ))}
      </div>
    </div>
  );
}
