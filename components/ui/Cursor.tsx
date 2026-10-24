"use client";

import { useEffect, useRef } from "react";
import { gsap } from "@/lib/gsap";

const HOVERABLE = "a, button, [role='button'], input, select, textarea, label, [data-cursor]";

/** Small ring that trails the pointer and grows over interactive elements. Desktop only. */
export function Cursor() {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const fine = window.matchMedia("(pointer: fine)").matches;
    const el = ref.current;
    if (!fine || !el) return;
    document.documentElement.classList.add("has-cursor");

    const xTo = gsap.quickTo(el, "x", { duration: 0.35, ease: "power3.out" });
    const yTo = gsap.quickTo(el, "y", { duration: 0.35, ease: "power3.out" });
    let shown = false;

    const move = (e: PointerEvent) => {
      xTo(e.clientX);
      yTo(e.clientY);
      if (!shown) {
        shown = true;
        gsap.set(el, { x: e.clientX, y: e.clientY });
        gsap.to(el, { autoAlpha: 1, duration: 0.3 });
      }
    };
    const over = (e: PointerEvent) => {
      const hit = (e.target as Element | null)?.closest?.(HOVERABLE);
      gsap.to(el.firstElementChild, { scale: hit ? 2.6 : 1, duration: 0.4, ease: "expo" });
      el.classList.toggle("is-hover", !!hit);
    };
    const leave = () => gsap.to(el, { autoAlpha: 0, duration: 0.2 });
    const enter = () => shown && gsap.to(el, { autoAlpha: 1, duration: 0.2 });

    window.addEventListener("pointermove", move, { passive: true });
    window.addEventListener("pointerover", over, { passive: true });
    document.addEventListener("pointerleave", leave);
    document.addEventListener("pointerenter", enter);
    return () => {
      document.documentElement.classList.remove("has-cursor");
      window.removeEventListener("pointermove", move);
      window.removeEventListener("pointerover", over);
      document.removeEventListener("pointerleave", leave);
      document.removeEventListener("pointerenter", enter);
    };
  }, []);

  return (
    <div
      ref={ref}
      aria-hidden
      className="pointer-events-none fixed left-0 top-0 z-[90] hidden opacity-0 mix-blend-difference [@media(pointer:fine)]:block"
    >
      <div className="-ml-[9px] -mt-[9px] h-[18px] w-[18px] rounded-full border border-white transition-[background-color] duration-300 [.is-hover>&]:bg-white/10" />
    </div>
  );
}
