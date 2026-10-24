"use client";

import { useRef } from "react";
import { gsap, useGSAP, MQ } from "@/lib/gsap";
import { ocean } from "@/content/home";
import { GhostHeading, buildGhostTl } from "@/components/text/GhostHeading";
import { Icon, type IconName } from "@/components/ui/Icon";
import { ShipTop } from "@/components/illustrations/ShipTop";
import { cloudTexture, wakeTexture, waterTexture } from "@/lib/textures";
import { cn } from "@/lib/cn";

/**
 * SCENE 6 — the ocean. Opens extremely close on the ship's container rows, then the camera climbs
 * (scale 3 → 0.35) while the ship travels up the frame. Heading, then four features around the ship,
 * then cloud banks roll in from the edges until the frame is white-blue sky (handed to Scene 7).
 */
export function Scene6Ocean() {
  const root = useRef<HTMLElement>(null);

  useGSAP(
    () => {
      const el = root.current!;
      // Paint the procedural textures once.
      el.querySelectorAll<HTMLElement>("[data-water]").forEach((w) => (w.style.backgroundImage = `url(${waterTexture()})`));
      el.querySelector<HTMLImageElement>("[data-wake]")!.src = wakeTexture();
      el.querySelectorAll<HTMLImageElement>("[data-cloud]").forEach((c, i) => (c.src = cloudTexture(11 + i * 7)));

      const mm = gsap.matchMedia();
      mm.add({ motion: MQ.motion, mobile: MQ.mobile }, (ctx) => {
        if (!ctx.conditions?.motion) return;
        const ghost = buildGhostTl(el.querySelector<HTMLElement>("[data-ghost-heading]")!);
        ghost.tl.pause(0);

        const tl = gsap.timeline({
          defaults: { ease: "none" },
          scrollTrigger: {
            trigger: el,
            start: "top top",
            end: ctx.conditions.mobile ? "+=380%" : "+=520%",
            pin: true,
            scrub: 1,
            anticipatePin: 1,
          },
        });

        tl.fromTo("[data-ship-cam]", { scale: 3.2, yPercent: 8 }, { scale: 0.34, yPercent: -22, duration: 10, ease: "power1.inOut" }, 0)
          .fromTo("[data-water-near]", { scale: 2.4 }, { scale: 0.7, duration: 10, ease: "power1.inOut" }, 0)
          .fromTo("[data-water-far]", { scale: 1.8 }, { scale: 0.9, duration: 10, ease: "power1.inOut" }, 0)
          // heading over the ship
          .add(ghost.tl.tweenFromTo(0, ghost.tl.duration(), { duration: 1.4 }), 1.6)
          .to("[data-ocean-heading]", { yPercent: -160, autoAlpha: 0, duration: 1.6, ease: "power1.in" }, 3.6)
          // features around the ship
          .fromTo(
            "[data-feature]",
            { autoAlpha: 0, y: 30 },
            { autoAlpha: 1, y: 0, duration: 0.8, stagger: 0.35, ease: "power2.out" },
            4.2,
          )
          .fromTo("[data-feature-ghost]", { autoAlpha: 0.55, y: 22 }, { autoAlpha: 0, y: 0, duration: 0.9, stagger: 0.35 }, 4.2)
          .to("[data-feature]", { autoAlpha: 0, y: -30, duration: 0.6, stagger: 0.1 }, 7)
          // clouds roll in from the edges and close over the frame
          .fromTo("[data-cloud='0']", { autoAlpha: 0, xPercent: -80, yPercent: -30, scale: 1.4 }, { autoAlpha: 0.95, xPercent: -10, yPercent: -10, scale: 1, duration: 2.6, ease: "power1.out" }, 7.2)
          .fromTo("[data-cloud='1']", { autoAlpha: 0, xPercent: 80, yPercent: 20, scale: 1.4 }, { autoAlpha: 0.95, xPercent: 5, yPercent: 0, scale: 1, duration: 2.6, ease: "power1.out" }, 7.4)
          .fromTo("[data-cloud='2']", { autoAlpha: 0, yPercent: 90, scale: 1.6 }, { autoAlpha: 0.95, yPercent: 10, scale: 1.1, duration: 2.6, ease: "power1.out" }, 7.6)
          .fromTo("[data-sky]", { autoAlpha: 0 }, { autoAlpha: 1, duration: 1.6 }, 8.6);

        return () => ghost.restore();
      });
    },
    { scope: root },
  );

  return (
    <section
      ref={root}
      data-scene="ocean"
      data-nav-theme="dark"
      className="relative h-[100svh] overflow-hidden bg-[#0a3a9e] text-paper motion-reduce:h-auto motion-reduce:min-h-screen"
      aria-labelledby="ocean-title"
    >
      {/* Water: two drifting layers at different scales for depth */}
      <div data-water-far data-water className="absolute -inset-1/2 animate-[drift_60s_linear_infinite] bg-[length:1024px] opacity-70" />
      <div data-water-near data-water className="absolute -inset-1/2 animate-[drift_40s_linear_infinite_reverse] bg-[length:760px] opacity-40" />
      <div aria-hidden className="absolute inset-0 bg-[radial-gradient(ellipse_at_center,transparent_30%,rgba(3,20,70,0.55)_100%)]" />

      {/* Ship + wake */}
      <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
        <div data-ship-cam className="relative w-[clamp(120px,15vw,230px)] will-change-transform">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img data-wake alt="" className="absolute left-1/2 top-[-6%] w-[250%] max-w-none -translate-x-1/2" />
          <ShipTop className="relative h-auto w-full drop-shadow-[0_18px_30px_rgba(0,10,40,0.45)]" />
        </div>
      </div>

      {/* Heading */}
      <div data-ocean-heading className="absolute inset-x-0 top-[38%] z-10 flex justify-center px-gutter text-center">
        <GhostHeading
          as="h2"
          lines={ocean.headline}
          mode="external"
          className="text-[clamp(1.8rem,4.6vw,4.6rem)] leading-[0.94] [&_.block]:whitespace-normal md:[&_.block]:whitespace-nowrap"
        />
        <span id="ocean-title" className="sr-only">
          {ocean.headline.join(" ")}
        </span>
      </div>

      {/* Features: two left, two right of the ship */}
      <div className="absolute inset-0 z-10 grid grid-cols-2 content-center gap-x-[min(34vw,520px)] gap-y-[14vh] px-gutter max-md:gap-x-6 max-md:content-end max-md:pb-[10vh]">
        {ocean.features.map((f, i) => (
          <div key={f.title} data-feature className={cn("relative max-w-[28ch] opacity-0 motion-reduce:opacity-100", i % 2 === 0 ? "justify-self-end text-right" : "justify-self-start")}>
            <div className={cn("relative mb-3 inline-block", i % 2 === 0 && "ml-auto")}>
              <Icon name={f.icon as IconName} className="h-7 w-7" />
            </div>
            <h3 className="display relative text-[clamp(14px,1.4vw,19px)] leading-tight">
              <span data-feature-ghost aria-hidden className="absolute inset-0 opacity-0">
                {f.title}
              </span>
              {f.title}
            </h3>
            <p className="mt-2 text-[13px] leading-snug text-paper/70 max-md:hidden">{f.body}</p>
          </div>
        ))}
      </div>

      {/* Cloud banks + sky */}
      {[0, 1, 2].map((i) => (
        // eslint-disable-next-line @next/next/no-img-element
        <img
          key={i}
          data-cloud={i}
          alt=""
          aria-hidden
          className={cn(
            "pointer-events-none invisible absolute z-20 w-[120vw] max-w-none opacity-0 motion-reduce:hidden",
            i === 0 && "-left-[10vw] top-[-10vh]",
            i === 1 && "-right-[10vw] top-[20vh]",
            i === 2 && "-left-[10vw] bottom-[-20vh]",
          )}
        />
      ))}
      <div data-sky aria-hidden className="pointer-events-none absolute inset-0 z-30 bg-[#e9eff8] opacity-0 motion-reduce:hidden" />
    </section>
  );
}
