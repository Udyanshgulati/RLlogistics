"use client";

import { useRef, type ElementType } from "react";
import { gsap, SplitText, useGSAP, MQ } from "@/lib/gsap";
import { tokenColor } from "@/lib/tokens";
import { cn } from "@/lib/cn";

type Props = {
  children: string;
  as?: ElementType;
  className?: string;
  /** "light" = steel → ink on paper, "dark" = steel → paper on ink. */
  tone?: "light" | "dark";
  /** "self" scrubs against its own position; "external" leaves it to a scene via buildScrubTl(). */
  mode?: "self" | "external";
  start?: string;
  end?: string;
};

/** Words start at steel @25% and turn to full ink one by one. */
export function buildScrubTl(el: HTMLElement, tone: "light" | "dark" = "light") {
  const split = SplitText.create(el, { type: "words" });
  const tl = gsap.timeline();
  tl.fromTo(
    split.words,
    { color: tokenColor("steel", 0.25) },
    { color: tokenColor(tone === "light" ? "ink" : "paper"), ease: "none", stagger: 0.1, duration: 0.3 },
  );
  return { tl, split };
}

export function ScrubParagraph({
  children,
  as: Tag = "p",
  className,
  tone = "light",
  mode = "self",
  start = "top 80%",
  end = "bottom 40%",
}: Props) {
  const ref = useRef<HTMLElement>(null);

  useGSAP(
    () => {
      const el = ref.current;
      if (!el || mode === "external") return;
      const mm = gsap.matchMedia();
      mm.add(MQ.motion, () => {
        const { tl, split } = buildScrubTl(el, tone);
        tl.pause();
        gsap.to(tl, { progress: 1, ease: "none", scrollTrigger: { trigger: el, start, end, scrub: true } });
        return () => split.revert();
      });
    },
    { scope: ref },
  );

  return (
    <Tag ref={ref} className={cn(tone === "light" ? "text-ink" : "text-paper", className)} data-scrub>
      {children}
    </Tag>
  );
}
