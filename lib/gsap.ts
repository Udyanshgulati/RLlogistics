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
  gsap.defaults({ ease: "power2.out", duration: 0.6 });
  registered = true;
}

/** One-shot reveal ease (expo-out). Scrubbed tweens should use "none" / "power1.inOut". */
export const EXPO = "expo";

/** Media query strings used with gsap.matchMedia() across scenes. */
export const MQ = {
  motion: "(prefers-reduced-motion: no-preference)",
  reduced: "(prefers-reduced-motion: reduce)",
  desktop: "(min-width: 768px)",
  mobile: "(max-width: 767px)",
} as const;

export { gsap, ScrollTrigger, SplitText, ScrambleTextPlugin, CustomEase, useGSAP };
