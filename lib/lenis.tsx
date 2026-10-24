"use client";

import Lenis from "lenis";
import { usePathname } from "next/navigation";
import { createContext, useContext, useEffect, useState, type ReactNode } from "react";
import { gsap, ScrollTrigger } from "@/lib/gsap";

const LenisContext = createContext<Lenis | null>(null);

export const useLenis = () => useContext(LenisContext);

export function LenisProvider({ children }: { children: ReactNode }) {
  const [lenis, setLenis] = useState<Lenis | null>(null);

  useEffect(() => {
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;

    const instance = new Lenis({ lerp: 0.1, wheelMultiplier: 1, smoothWheel: true });
    instance.on("scroll", ScrollTrigger.update);
    const tick = (time: number) => instance.raf(time * 1000);
    gsap.ticker.add(tick);
    gsap.ticker.lagSmoothing(0);
    setLenis(instance);

    // Re-measure once fonts settle — headline metrics drive most pin lengths.
    document.fonts?.ready.then(() => ScrollTrigger.refresh());

    return () => {
      gsap.ticker.remove(tick);
      instance.destroy();
      setLenis(null);
    };
  }, []);

  // New page: jump to the top (or the hash target) and re-measure every trigger.
  const pathname = usePathname();
  useEffect(() => {
    const hash = window.location.hash;
    const target = hash ? document.querySelector<HTMLElement>(hash) : null;
    if (target) lenis?.scrollTo(target, { immediate: true });
    else lenis?.scrollTo(0, { immediate: true });
    const id = requestAnimationFrame(() => ScrollTrigger.refresh());
    return () => cancelAnimationFrame(id);
  }, [pathname, lenis]);

  return <LenisContext.Provider value={lenis}>{children}</LenisContext.Provider>;
}

/** Scroll to a target with Lenis when available, native otherwise. */
export function scrollToTarget(lenis: Lenis | null, target: number | string | HTMLElement) {
  if (lenis) return lenis.scrollTo(target, { duration: 1.6 });
  const top =
    typeof target === "number"
      ? target
      : (typeof target === "string" ? document.querySelector<HTMLElement>(target) : target)?.offsetTop ?? 0;
  window.scrollTo({ top, behavior: "smooth" });
}
