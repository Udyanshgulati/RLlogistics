"use client";

import { useRef } from "react";
import { gsap, useGSAP, EXPO } from "@/lib/gsap";

// The intro loader covers the very first page; the wipe only plays on route changes.
let firstMount = true;

/** Page transition: an orange panel that lifts off each newly mounted page. */
export default function Template({ children }: { children: React.ReactNode }) {
  const panel = useRef<HTMLDivElement>(null);

  useGSAP(() => {
    const el = panel.current!;
    if (firstMount || window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      firstMount = false;
      gsap.set(el, { display: "none" });
      return;
    }
    gsap
      .timeline()
      .set(el, { display: "block", yPercent: 0 })
      .to(el, { yPercent: -100, duration: 0.9, ease: EXPO, delay: 0.1 })
      .set(el, { display: "none" });
  });

  return (
    <>
      <div ref={panel} aria-hidden className="fixed inset-0 z-[95] hidden bg-brand" />
      {children}
    </>
  );
}
