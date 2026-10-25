"use client";

import { gsap } from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { SplitText } from "gsap/SplitText";
import { ScrambleTextPlugin } from "gsap/ScrambleTextPlugin";
import { CustomEase } from "gsap/CustomEase";
import { useGSAP } from "@gsap/react";

let registered = false;

if (typeof window !== "undefined" && !registered) {
  gsap.registerPlugin(ScrollTrigger, SplitText, ScrambleTextPlugin, CustomEase, useGSAP);
  CustomEase.create("expo", "0.16, 1, 0.3, 1");
  // iOS/Android show and hide the address bar while scrolling, which resizes the viewport. Without this every
  // such resize triggers a full refresh and the pinned scenes jump.
  ScrollTrigger.config({ ignoreMobileResize: true });
  // Re-measure once web fonts settle (headline metrics drive pin lengths) and after a rotation.
  document.fonts?.ready.then(() => ScrollTrigger.refresh());
  window.addEventListener("orientationchange", () => setTimeout(() => ScrollTrigger.refresh(), 300));
  gsap.defaults({ ease: "power2.out", duration: 0.6 });
  registered = true;
}

/** One-shot reveal ease (expo-out). Scrubbed tweens should use "none" / "power1.inOut". */
export const EXPO = "expo";

/** Media query strings used with gsap.matchMedia() across scenes. */
export const MQ = {
  motion: "(prefers-reduced-motion: no-preference)",
  reduced: "(prefers-reduced-motion: reduce)",
  // Phones and tablets (incl. landscape phones) share the mobile compositions; desktop starts at 1024px (Tailwind lg).
  desktop: "(min-width: 1024px)",
  mobile: "(max-width: 1023px)",
  /** Touch-first devices: no custom cursor, no magnetic hover. */
  coarse: "(pointer: coarse)",
} as const;

export { gsap, ScrollTrigger, SplitText, ScrambleTextPlugin, CustomEase, useGSAP };
