"use client";

import { useRef } from "react";
import { gsap, useGSAP, MQ } from "@/lib/gsap";
import { ocean } from "@/content/home";
import { GhostHeading, buildGhostTl } from "@/components/text/GhostHeading";
import { Icon, type IconName } from "@/components/ui/Icon";
import { PortTerminal } from "@/components/illustrations/PortTerminal";
import { cloudTexture, whenNear } from "@/lib/textures";
import { cn } from "@/lib/cn";
import type { AssetOverrides } from "@/lib/asset-slots";

/**
 * SCENE 6 — the port. Opens close on a quay crane working the ship at Berth 3, then the camera pulls
 * back over the whole terminal while trucks run the lane and the amber route to Delhi draws in.
 * Four features sit in a panel at the bottom; then cloud banks roll in to hand off to Scene 7.
 * (File/export name kept from the earlier ocean scene so the page imports don't change.)
 */
export function Scene6Ocean({ assets = {} }: { assets?: AssetOverrides }) {
  const root = useRef<HTMLElement>(null);

  useGSAP(
    () => {
      const el = root.current!;
      // Paint the procedural textures once, in idle time, as the scene approaches.
      const clouds = [assets.cloud1, assets.cloud2, assets.cloud3];
      const stopNear = whenNear(el, [
        ...[0, 1, 2].map((i) => () => {
          const img = el.querySelector<HTMLImageElement>(`[data-cloud='${i}']`);
          if (img) img.src = clouds[i] ?? cloudTexture(11 + i * 7);
        }),
      ]);

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

        // Camera: from a close-up of the middle crane to the whole terminal.
        tl.fromTo("[data-port-cam]", { scale: 1.7 }, { scale: 1, duration: 10, ease: "power1.inOut" }, 0)
          .fromTo("[data-ocean-blend]", { autoAlpha: 1 }, { autoAlpha: 0, duration: 1.2, ease: "power1.out" }, 0)
          // Cranes shuffle along the quay; trolleys shuttle boxes ship ↔ quay; trucks run the lane.
          .fromTo("[data-crane]", { x: 0 }, { x: (i: number) => [-40, 30, -25][i] ?? 0, duration: 10 }, 0)
          .fromTo("[data-trolley]", { y: 0 }, { y: 250, duration: 1.2, ease: "sine.inOut", yoyo: true, repeat: 7, stagger: 0.4 }, 0)
          .fromTo("[data-truck]", { x: -260 }, { x: 460, duration: 10, stagger: 0.15 }, 0)
          .fromTo("[data-rtg]", { x: 0 }, { x: -220, duration: 10, ease: "sine.inOut" }, 0)
          .fromTo("[data-route]", { strokeDashoffset: 1 }, { strokeDashoffset: 0, duration: 4, ease: "power2.out" }, 2.4)
          // heading
          .add(ghost.tl.tweenFromTo(0, ghost.tl.duration(), { duration: 1.4 }), 1.2)
          // features panel
          .fromTo("[data-feature]", { autoAlpha: 0, y: 30 }, { autoAlpha: 1, y: 0, duration: 0.8, stagger: 0.25, ease: "power2.out" }, 3.4)
          .to("[data-ocean-heading], [data-feature]", { autoAlpha: 0, y: -24, duration: 0.6, stagger: 0.05 }, 6.6)
          // Clouds roll in from the edges while the ship is still visible below them (scale ≤ 1.15 keeps them crisp)
          .fromTo("[data-cloud='0']", { autoAlpha: 0, xPercent: -70, yPercent: -25, scale: 1.15 }, { autoAlpha: 0.95, xPercent: -10, yPercent: -10, scale: 1, duration: 2.2, ease: "power1.out" }, 6.9)
          .fromTo("[data-cloud='1']", { autoAlpha: 0, xPercent: 70, yPercent: 15, scale: 1.15 }, { autoAlpha: 0.95, xPercent: 5, yPercent: 0, scale: 1, duration: 2.2, ease: "power1.out" }, 7.1)
          .fromTo("[data-cloud='2']", { autoAlpha: 0, yPercent: 80, scale: 1.15 }, { autoAlpha: 0.95, yPercent: 10, scale: 1, duration: 2.2, ease: "power1.out" }, 7.3)
          // Sky closes exactly as the ship's zoom-out ends, so the frame hands off to the plane without a hold.
          .fromTo("[data-sky]", { autoAlpha: 0 }, { autoAlpha: 1, duration: 1.1 }, 8.9);

        return () => ghost.restore();
      });
      return stopNear;
    },
    { scope: root },
  );

  return (
    <section
      ref={root}
      data-scene="ocean"
      data-nav-theme="dark"
      className="relative h-[100svh] overflow-hidden bg-[#0b3aa8] text-paper motion-reduce:h-auto motion-reduce:min-h-screen"
      aria-labelledby="ocean-title"
    >
      {/* Terminal, seen from above */}
      <div data-port-cam className="absolute inset-0 origin-[50%_28%]">
        <PortTerminal className="h-full w-full" />
      </div>
      {/* Legibility: navy wash behind the heading (top-left) and the feature panel (bottom). */}
      <div aria-hidden className="pointer-events-none absolute inset-0 bg-[linear-gradient(100deg,rgba(11,31,77,0.9)_0%,rgba(11,31,77,0.55)_40%,transparent_66%),linear-gradient(to_top,rgba(11,31,77,0.88),transparent_38%),linear-gradient(to_bottom,rgba(11,31,77,0.7),transparent_20%)]" />
      {/* Matches the road's blue tint so the hand-off has no seam; fades once the pin starts. */}
      <div data-ocean-blend aria-hidden className="pointer-events-none absolute inset-x-0 top-0 z-[5] h-[70%] bg-[linear-gradient(to_bottom,#0a3a9e_25%,rgba(10,58,158,0))] motion-reduce:hidden" />
      <div aria-hidden className="grain pointer-events-none absolute inset-0 opacity-[0.05]" />

      {/* Heading */}
      <div data-ocean-heading className="absolute left-gutter top-[18vh] z-10 lg:left-[clamp(140px,13vw,220px)]">
        <p className="eyebrow mb-5 text-brand">RLWU 04 · Port &amp; ocean</p>
        <GhostHeading as="h2" lines={ocean.headline} mutedLines={[1]} mode="external" className="text-[clamp(1.8rem,4.2vw,4.4rem)] leading-[1]" />
        <span id="ocean-title" className="sr-only">
          {ocean.headline.join(" ")}
        </span>
        <p className="mt-6 max-w-[38ch] text-[15px] leading-relaxed text-paper/75">
          Customs-cleared at JNPT or Mundra, on our trucks the same day and in your warehouse in Delhi NCR inside 36 hours.
        </p>
      </div>

      {/* Features: one panel along the bottom */}
      <div className="absolute inset-x-0 bottom-[6vh] z-10 grid grid-cols-4 gap-4 px-gutter lg:pl-[clamp(140px,13vw,220px)]">
        {ocean.features.map((f) => (
          <div key={f.title} data-feature className="corner-cast relative rounded-[10px] bg-ink/75 p-5 opacity-0 backdrop-blur-md motion-reduce:opacity-100">
            <Icon name={f.icon as IconName} className="h-6 w-6 text-brand" />
            <h3 className="mt-3 text-[16px] font-semibold leading-snug">{f.title}</h3>
            <p className="mt-1.5 text-[13px] leading-snug text-paper/70">{f.body}</p>
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
