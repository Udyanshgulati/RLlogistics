"use client";

import Lenis from "lenis";
import { usePathname } from "next/navigation";
import { createContext, useContext, useEffect, useRef, useState, type ReactNode } from "react";
import { gsap, ScrollTrigger } from "@/lib/gsap";

const LenisContext = createContext<Lenis | null>(null);

export const useLenis = () => useContext(LenisContext);

export function LenisProvider({ children }: { children: ReactNode }) {
  const [lenis, setLenis] = useState<Lenis | null>(null);

  useEffect(() => {
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
    // Visual test harness (scripts/snap.ts) loads ?test=1 to scroll with exact, unsmoothed positions.
    if (new URLSearchParams(window.location.search).has("test")) return;
    // Phones and tablets scroll natively (iOS momentum, no fighting the address bar); smoothing is desktop-only.
    if (!window.matchMedia("(min-width: 1024px)").matches) return;

    // Smoothing only for wheel/trackpad; touch keeps native scrolling + iOS momentum.
    const instance = new Lenis({ lerp: 0.1, wheelMultiplier: 1, smoothWheel: true, syncTouch: false, touchMultiplier: 1 });
    (window as Window & { __LENIS__?: Lenis }).__LENIS__ = instance;
    instance.on("scroll", ScrollTrigger.update);
    const tick = (time: number) => instance.raf(time * 1000);
    gsap.ticker.add(tick);
    gsap.ticker.lagSmoothing(0);
    setLenis(instance);

    return () => {
      gsap.ticker.remove(tick);
      instance.destroy();
      setLenis(null);
    };
  }, []);

  // New page: jump to the top (or the hash target) and re-measure every trigger.
  const pathname = usePathname();
  const lastPath = useRef(pathname);
  useEffect(() => {
    // First load: ScrollTrigger already measures as the scenes mount; only re-measure on navigation.
    if (lastPath.current === pathname) return;
    lastPath.current = pathname;
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
