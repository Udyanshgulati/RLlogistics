"use client";

import Link from "next/link";
import { useEffect, useRef } from "react";
import { gsap } from "@/lib/gsap";
import { Arrow } from "./Button";

/** Circular CTA that leans toward the cursor within a radius (desktop pointers only). */
export function MagneticButton({ href, children, radius = 160 }: { href: string; children: React.ReactNode; radius?: number }) {
  const ref = useRef<HTMLAnchorElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el || !window.matchMedia("(pointer: fine)").matches) return;
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
    const xTo = gsap.quickTo(el, "x", { duration: 0.6, ease: "expo" });
    const yTo = gsap.quickTo(el, "y", { duration: 0.6, ease: "expo" });
    const label = el.querySelector("[data-label]");
    const move = (e: PointerEvent) => {
      const r = el.getBoundingClientRect();
      const dx = e.clientX - (r.left + r.width / 2);
      const dy = e.clientY - (r.top + r.height / 2);
      const d = Math.hypot(dx, dy);
      const pull = d < radius ? 0.35 : 0;
      xTo(dx * pull);
      yTo(dy * pull);
      gsap.to(label, { x: dx * pull * 0.4, y: dy * pull * 0.4, duration: 0.6, ease: "expo" });
    };
    window.addEventListener("pointermove", move, { passive: true });
    return () => window.removeEventListener("pointermove", move);
  }, [radius]);

  return (
    <Link
      ref={ref}
      href={href}
      className="group relative inline-flex h-[clamp(132px,13vw,184px)] w-[clamp(132px,13vw,184px)] items-center justify-center rounded-full bg-brand text-ink transition-colors duration-500 hover:bg-ink hover:text-paper"
    >
      <span data-label className="flex flex-col items-center gap-2 text-[14px] font-medium">
        {children}
        <Arrow className="h-4 w-4 transition-transform duration-500 ease-expo group-hover:translate-x-1" />
      </span>
    </Link>
  );
}
