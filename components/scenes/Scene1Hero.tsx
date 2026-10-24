"use client";

import dynamic from "next/dynamic";
import { useCallback, useEffect, useRef, useState } from "react";
import { gsap, useGSAP, EXPO, MQ } from "@/lib/gsap";
import { useLoader } from "@/lib/loader";
import { hero } from "@/content/home";
import { RevealHeading } from "@/components/text/RevealHeading";
import { Button } from "@/components/ui/Button";
import type { GlobeProgress } from "@/components/globe/Globe";

const Globe = dynamic(() => import("@/components/globe/Globe"), { ssr: false });
const GlobeFlat = dynamic(() => import("@/components/globe/GlobeFlat"), { ssr: false });

/**
 * SCENE 1 — hero + globe dive.
 * Pinned and scrubbed: text lifts away, the globe swings Delhi to centre and the camera falls through
 * the atmosphere into a blue → white haze that hands off to the paper of Scene 2.
 */
export function Scene1Hero() {
  const root = useRef<HTMLElement>(null);
  const { done, track } = useLoader();
  const progress = useRef<GlobeProgress>({ intro: 0, dive: 0 });
  const [inView, setInView] = useState(true);
  const [submerged, setSubmerged] = useState(false);
  const [offsetX, setOffsetX] = useState(1.02);
  const [flat, setFlat] = useState<boolean | null>(null);

  // The loader waits for the globe's first rendered frame.
  const readyRef = useRef<() => void>(() => {});
  useEffect(() => {
    track(new Promise<void>((resolve) => (readyRef.current = resolve)));
  }, [track]);

  const onGlobeReady = useCallback(() => readyRef.current(), []);

  // Pause the WebGL loop when the hero is off-screen or fully covered by the haze.
  useEffect(() => {
    const el = root.current;
    if (!el) return;
    const io = new IntersectionObserver(([e]) => setInView(e.isIntersecting), { rootMargin: "100px" });
    io.observe(el);
    // Phones get the lightweight 2D canvas globe; decided once so the canvas isn't swapped mid-scroll.
    setFlat(window.innerWidth < 768);
    const onResize = () => setOffsetX(window.innerWidth < 768 ? 0 : window.innerWidth < 1100 ? 0.8 : 1.02);
    onResize();
    window.addEventListener("resize", onResize);
    return () => {
      io.disconnect();
      window.removeEventListener("resize", onResize);
    };
  }, []);

  // Load-in once the loader has wiped away.
  useGSAP(
    () => {
      if (!done) {
        gsap.set("[data-hero-fade]", { autoAlpha: 0 });
        return;
      }
      const mm = gsap.matchMedia();
      mm.add(MQ.motion, () => {
        gsap.to(progress.current, { intro: 1, duration: 2, ease: EXPO });
        gsap.fromTo("[data-hero-fade]", { autoAlpha: 0, y: 24 }, { autoAlpha: 1, y: 0, duration: 1.1, ease: EXPO, stagger: 0.08, delay: 0.7 });
      });
      mm.add(MQ.reduced, () => {
        progress.current.intro = 1;
        gsap.set("[data-hero-fade]", { autoAlpha: 1 });
      });
    },
    { scope: root, dependencies: [done] },
  );

  // Scrubbed dive.
  useGSAP(
    () => {
      const mm = gsap.matchMedia();
      mm.add({ desktop: MQ.desktop, motion: MQ.motion }, (ctx) => {
        if (!ctx.conditions?.motion) return;
        const tl = gsap.timeline({
          defaults: { ease: "none" },
          scrollTrigger: {
            trigger: root.current,
            start: "top top",
            end: ctx.conditions.desktop ? "+=180%" : "+=120%",
            pin: true,
            scrub: 1,
            anticipatePin: 1,
            onUpdate: (self) => {
              progress.current.dive = self.progress;
              setSubmerged(self.progress > 0.985);
              // Nav switches to its light theme once the haze has whitened.
              root.current?.setAttribute("data-nav-theme", self.progress > 0.62 ? "light" : "dark");
            },
          },
        });
        // 0 → 0.4: copy lifts and fades
        tl.to("[data-hero-copy]", { yPercent: -18, autoAlpha: 0, duration: 0.36, ease: "power1.in" }, 0)
          .to("[data-hero-meta]", { autoAlpha: 0, duration: 0.2 }, 0)
          // 0.4 → 0.7: entering the atmosphere
          // 0.4 → 0.62: land dots dissolve as we fall through them
          .fromTo("[data-hero-globe]", { filter: "blur(0px)" }, { filter: "blur(14px)", duration: 0.2, ease: "power1.in" }, 0.4)
          .set("[data-hero-globe]", { filter: "none" }, 0.75)
          .fromTo("[data-atmos]", { autoAlpha: 0, scale: 1.15 }, { autoAlpha: 1, scale: 1, duration: 0.3, ease: "power1.inOut" }, 0.42)
          // 0.7 → 1: haze whitens into paper
          .fromTo("[data-haze]", { autoAlpha: 0 }, { autoAlpha: 1, duration: 0.28, ease: "power1.inOut" }, 0.72);
      });
    },
    { scope: root },
  );

  return (
    <section ref={root} data-scene="hero" data-nav-theme="dark" className="relative h-[100svh] overflow-hidden bg-ink text-paper" aria-label="Introduction">
      {/* Globe */}
      <div data-hero-globe className="absolute inset-0">
        {flat === true && <GlobeFlat progress={progress} active={inView && !submerged} onReady={onGlobeReady} />}
        {flat === false && <Globe progress={progress} active={inView && !submerged} offsetX={offsetX} onReady={onGlobeReady} />}
      </div>
      {/* Legibility gradient behind copy on small screens */}
      <div aria-hidden className="pointer-events-none absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-ink via-ink/70 to-transparent md:hidden" />

      {/* Copy */}
      <div data-hero-copy className="relative z-10 flex h-full flex-col justify-end px-gutter pb-[clamp(28px,6vh,64px)] md:pl-[clamp(140px,13vw,220px)]">
        <p data-hero-fade className="eyebrow mb-5 flex items-center gap-3 text-brand">
          <span aria-hidden className="h-px w-6 bg-brand" />
          {hero.eyebrow}
        </p>
        <RevealHeading
          as="h1"
          lines={hero.headline}
          mode="manual"
          play={done}
          delay={0.35}
          className="text-[clamp(2.6rem,6.2vw,7.5rem)] leading-[0.9] tracking-[-0.02em]"
        />
        <p data-hero-fade className="mt-7 max-w-[44ch] text-[15px] leading-relaxed text-paper/70">
          {hero.sub}
        </p>
        <div data-hero-fade className="mt-7 flex flex-wrap gap-3">
          <Button href={hero.primary.href} variant="filled" tone="dark">
            {hero.primary.label}
          </Button>
          <Button href={hero.secondary.href} variant="ghost" tone="dark">
            {hero.secondary.label}
          </Button>
        </div>
      </div>

      {/* Bottom-right meta */}
      <div data-hero-meta className="absolute bottom-[clamp(28px,6vh,64px)] right-gutter z-10 hidden flex-col items-end gap-2 md:flex">
        <span data-hero-fade className="ui-label text-steel">
          {hero.coords}
        </span>
        <span data-hero-fade className="ui-label flex items-center gap-3 text-paper/80">
          {hero.scrollHint}
          <span aria-hidden className="relative block h-8 w-px overflow-hidden bg-paper/20">
            <span className="absolute inset-x-0 top-0 h-1/2 animate-[scrollcue_1.8s_ease-in-out_infinite] bg-brand" />
          </span>
        </span>
      </div>

      {/* Atmosphere: saturated blue from above, white haze rising from below */}
      <div
        data-atmos
        aria-hidden
        className="pointer-events-none invisible absolute inset-0 z-20 opacity-0"
        style={{
          background:
            "linear-gradient(to bottom, rgb(var(--ocean)) 0%, rgb(var(--ocean-bright)) 22%, rgb(150 185 255) 48%, rgb(var(--paper)) 82%)",
        }}
      />
      <div data-haze aria-hidden className="pointer-events-none invisible absolute inset-0 z-20 bg-paper opacity-0" />
    </section>
  );
}
