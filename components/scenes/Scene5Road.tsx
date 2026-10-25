"use client";

import { useRef } from "react";
import { gsap, useGSAP, MQ } from "@/lib/gsap";
import { road } from "@/content/home";
import { GhostHeading, buildGhostTl } from "@/components/text/GhostHeading";
import { Icon, type IconName } from "@/components/ui/Icon";
import { TruckTop } from "@/components/illustrations/TruckTop";
import { cn } from "@/lib/cn";
import type { AssetOverrides } from "@/lib/asset-slots";

/**
 * SCENE 5 — the road. Starts as a giant black S-curve that fills the screen (continuing Scene 4's dark panel),
 * then pulls back and straightens into a vertical strip. A top-down truck drives down it and each milestone
 * draws its connector as the truck passes. Exit: the page tints blue from the bottom into the ocean.
 */

// Vertical position (fraction of viewport) and side for each milestone.
const SLOTS = [
  { y: 0.24, side: "right" },
  { y: 0.47, side: "left" },
  { y: 0.58, side: "right" },
  { y: 0.72, side: "left" },
  { y: 0.85, side: "right" },
] as const;

/** On phones the heading takes the top third; milestones stack evenly in one column below it. */
const slotY = (i: number, mobile: boolean) => (mobile ? 0.34 + i * 0.13 : SLOTS[i].y);

export function Scene5Road({ assets = {} }: { assets?: AssetOverrides }) {
  const root = useRef<HTMLElement>(null);

  useGSAP(
    () => {
      const el = root.current!;
      const svg = el.querySelector<SVGSVGElement>("[data-road-svg]")!;
      const asphalt = el.querySelector<SVGPathElement>("[data-asphalt]")!;
      const lane = el.querySelector<SVGPathElement>("[data-lane]")!;
      const edges = el.querySelectorAll<SVGPathElement>("[data-edge]");
      const group = el.querySelector<SVGGElement>("[data-road-group]")!;
      const truck = el.querySelector<HTMLElement>("[data-truck]")!;

      const state = { curve: 1, scale: 4, dash: 0, truckY: -0.3 };
      let W = 0, H = 0, cx = 0, RW = 0, mobile = false;

      const measure = () => {
        W = window.innerWidth;
        H = window.innerHeight;
        mobile = W < 1024;
        cx = mobile ? W * 0.16 : W * 0.6;
        RW = mobile ? 74 : Math.min(190, W * 0.13);
        svg.setAttribute("viewBox", `0 0 ${W} ${H}`);
        asphalt.setAttribute("stroke-width", String(RW));
        edges.forEach((e) => e.setAttribute("stroke-width", String(RW + 6)));
        truck.style.width = `${RW * 0.4}px`;
        el.style.setProperty("--road-x", `${cx}px`);
        el.style.setProperty("--road-w", `${RW}px`);
      };

      const render = () => {
        const A = state.curve * W * 0.32;
        const d = `M ${cx + A} ${-H * 0.6} C ${cx + A} ${H * 0.25}, ${cx - A} ${H * 0.75}, ${cx - A} ${H * 1.6}`;
        asphalt.setAttribute("d", d);
        lane.setAttribute("d", d);
        edges.forEach((e) => e.setAttribute("d", d));
        // Keep lane markings at road scale while the camera is zoomed in (otherwise they become giant white bars).
        lane.setAttribute("stroke-width", String(3 / state.scale));
        lane.setAttribute("stroke-dasharray", `${34 / state.scale} ${46 / state.scale}`);
        lane.style.strokeDashoffset = String(state.dash / state.scale);
        group.setAttribute("transform", `translate(${cx} ${H / 2}) scale(${state.scale}) translate(${-cx} ${-H / 2})`);
        truck.style.transform = `translate3d(${cx + RW * 0.2 - (RW * 0.4) / 2}px, ${state.truckY * H}px, 0)`;
        truck.style.opacity = state.scale > 1.6 ? "0" : "1";
      };

      const mm = gsap.matchMedia();
      mm.add({ motion: MQ.motion, mobile: MQ.mobile }, (ctx) => {
        if (!ctx.conditions?.motion) return;
        measure();
        const ghost = buildGhostTl(el.querySelector<HTMLElement>("[data-ghost-heading]")!);
        ghost.tl.pause(0);

        const tl = gsap.timeline({
          defaults: { ease: "power1.inOut" },
          onUpdate: render,
          scrollTrigger: {
            trigger: el,
            start: "top top",
            end: ctx.conditions.mobile ? "+=280%" : "+=480%",
            pin: true,
            scrub: 1,
            anticipatePin: 1,
            invalidateOnRefresh: true,
            onRefresh: () => {
              measure();
              render();
            },
          },
        });

        // Transition: giant curve → straight vertical strip.
        // Phones start closer to the final framing so the screen isn't one big black curve.
        tl.fromTo(state, { scale: ctx.conditions.mobile ? 2 : 4 }, { scale: 1, duration: 2, ease: "power3.out" }, 0)
          .fromTo("[data-heading-wrap]", { autoAlpha: 0 }, { autoAlpha: 1, duration: 0.3 }, 1.7)
          .to(state, { curve: 0, duration: 2.2, ease: "power2.inOut" }, 0.3)
          .add(ghost.tl.tweenFromTo(0, ghost.tl.duration(), { duration: 1.2, ease: "none" }), 1.8)
          // Drive: truck moves down, lane dashes stream up.
          .to(state, { truckY: 0.92, duration: 7, ease: "none" }, 2.4)
          .to(state, { dash: 3200, duration: 7.8, ease: "none" }, 1.6);

        // Each milestone reveals as the truck passes its y.
        SLOTS.forEach((slot, i) => {
          const at = 2.4 + ((slotY(i, !!ctx.conditions?.mobile) + 0.3 - 0.12) / 1.22) * 7;
          const item = el.querySelector(`[data-milestone="${i}"]`);
          tl.fromTo(item!.querySelector("[data-connector]"), { scaleX: 0 }, { scaleX: 1, duration: 0.4, ease: "power2.out" }, at)
            .fromTo(item!.querySelector("[data-dot]"), { scale: 0 }, { scale: 1, duration: 0.2 }, at)
            .fromTo(item!.querySelectorAll("[data-ms-in]"), { autoAlpha: 0, y: 16 }, { autoAlpha: 1, y: 0, stagger: 0.08, duration: 0.5 }, at + 0.25);
        });

        // Exit: blue rises from the bottom.
        tl.to("[data-heading-wrap]", { autoAlpha: 0, y: -40, duration: 0.8 }, 9.2).fromTo(
          "[data-tint]",
          { autoAlpha: 1, yPercent: 100 },
          { autoAlpha: 1, yPercent: 0, duration: 1.6, ease: "power2.in" },
          9.4,
        );
        // Short hold on full blue so scrub lag never shows a seam at the hand-off to the ocean.
        tl.to({}, { duration: 0.4 });

        render();
        return () => ghost.restore();
      });

      mm.add(MQ.reduced, () => {
        measure();
        Object.assign(state, { curve: 0, scale: 1, truckY: 0.5 });
        render();
      });

      const onResize = () => {
        measure();
        render();
      };
      window.addEventListener("resize", onResize);
      return () => window.removeEventListener("resize", onResize);
    },
    { scope: root },
  );

  return (
    <section ref={root} data-scene="road" data-nav-theme="light" className="relative h-[100svh] overflow-hidden bg-paper" aria-labelledby="road-title">
      <svg data-road-svg className="absolute inset-0 h-full w-full" aria-hidden>
        <g data-road-group>
          <path data-edge fill="none" stroke="#c9ccd2" strokeLinecap="butt" />
          <path data-asphalt fill="none" stroke="#0e1117" strokeLinecap="butt" />
          <path data-lane fill="none" stroke="#f3f2ee" strokeWidth={3} strokeDasharray="34 46" />
        </g>
      </svg>

      <div data-truck className="absolute left-0 top-0 z-10">
        <TruckTop src={assets.truckTop} className="h-auto w-full" />
      </div>

      <div data-heading-wrap className="absolute left-[calc(var(--road-x)+var(--road-w)/2+20px)] top-[14vh] z-10 lg:left-[clamp(140px,13vw,220px)]">
        <p className="eyebrow mb-4 text-steel">{road.eyebrow}</p>
        <GhostHeading as="h2" lines={road.headline} mutedLines={[2]} mode="external" className="text-[clamp(1.6rem,7vw,3rem)] leading-[0.95] lg:text-[clamp(1.4rem,3.5vw,3.8rem)] lg:leading-[0.92]" />
        <span id="road-title" className="sr-only">
          {road.headline.join(" ")}
        </span>
      </div>

      {road.milestones.map((m, i) => {
        const slot = SLOTS[i];
        const right = slot.side === "right";
        return (
          <div
            key={m.title}
            data-milestone={i}
            className={cn(
              "absolute top-[calc(var(--y-m)-10px)] z-10 flex items-start lg:top-[calc(var(--y-d)-10px)]",
              // mobile: everything sits to the right of a road hugging the left edge
              "left-[calc(var(--road-x)+var(--road-w)/2)] right-gutter",
              right
                ? "lg:left-[calc(var(--road-x)+var(--road-w)/2)] lg:right-auto"
                : "lg:left-auto lg:right-[calc(100%-var(--road-x)+var(--road-w)/2)] lg:flex-row-reverse",
            )}
            style={{ "--y-d": `${slot.y * 100}%`, "--y-m": `${slotY(i, true) * 100}%` } as React.CSSProperties}
          >
            <span data-dot className="relative z-10 -ml-1 mt-[7px] h-2 w-2 shrink-0 rounded-full bg-brand lg:-mr-1 lg:ml-0" />
            <span data-connector className={cn("mt-[10px] h-px w-6 shrink-0 bg-ink/40 lg:w-16", right ? "origin-left" : "origin-left lg:origin-right")} />
            <div className={cn("max-w-[30ch] px-3", !right && "lg:text-right")}>
              <span data-ms-in className={cn("mb-2 flex max-lg:hidden", !right && "lg:justify-end")}>
                <Icon name={m.icon as IconName} className="h-6 w-6 text-ink" />
              </span>
              <h3 data-ms-in className="display text-[16px] leading-tight lg:text-[15px]">
                {m.title}
              </h3>
              <p data-ms-in className="mt-1.5 text-[14px] leading-snug text-ink/65 lg:text-[13px] lg:text-ink/60 [@media(max-height:720px)_and_(max-width:1023px)]:hidden">
                {m.body}
              </p>
            </div>
          </div>
        );
      })}

      <div
        data-tint
        aria-hidden
        className="pointer-events-none invisible absolute inset-x-0 top-[-60%] z-20 h-[160%]"
        style={{ background: "linear-gradient(to top, #0a3a9e 63%, rgba(10,58,158,0.55) 80%, rgba(10,58,158,0))" }}
      />
    </section>
  );
}
