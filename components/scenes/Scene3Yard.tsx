"use client";

import Link from "next/link";
import { useRef } from "react";
import { gsap, useGSAP, MQ } from "@/lib/gsap";
import { servicesSection } from "@/content/home";
import { homeServices } from "@/content/services";
import { GhostHeading, buildGhostTl } from "@/components/text/GhostHeading";
import { Marquee } from "@/components/text/Marquee";
import { Icon } from "@/components/ui/Icon";
import { Button } from "@/components/ui/Button";
import { ReachStacker, STACKER } from "@/components/illustrations/ReachStacker";
import { TruckSide, TRUCK } from "@/components/illustrations/TruckSide";
import { Container } from "@/components/illustrations/Container";

/**
 * SCENES 3 + 4 — the container yard handing off to services.
 * One pinned stage, one master timeline. The yard is a small kinematic rig: the timeline tweens a handful
 * of numbers (stacker x, boom angle/extension, truck x, camera) and render() places every SVG part from them,
 * so the whole sequence scrubs forwards and backwards exactly.
 */

// World (SVG viewBox) geometry
const VB = { w: 1600, h: 900 };
const GROUND = 760;
const C40 = { w: 410, h: 160 };
const C20 = { w: 200, h: 160 };
const STACK_X = 1040;
const WHITE_REST = { x: STACK_X - 3, y: GROUND - C20.h - C40.h }; // top-left of the white 40ft on the stack

type Pose = { a: number; ext: number };
const DEG = Math.PI / 180;

/** Boom angle/extension that puts the boom tip at (tx, ty) for a stacker at x = sx. */
function ik(sx: number, tx: number, ty: number): Pose {
  const px = sx + STACKER.pivot.x;
  const py = GROUND + STACKER.pivot.y;
  const dx = tx - px;
  const dy = py - ty;
  return { a: Math.atan2(dy, dx) / DEG, ext: Math.hypot(dx, dy) - STACKER.boomLength };
}

const SX_LOCK = 380;
const SX_CARRY = -70;
const P_REST: Pose = { a: 30, ext: 0 };
const P_LOCK = ik(SX_LOCK, WHITE_REST.x + C40.w / 2, WHITE_REST.y - STACKER.hang);
const P_LIFT = ik(SX_LOCK, 1100, 225);
const P_CARRY = { ...P_LIFT };
const TIP_CARRY_X = 1100 - (SX_LOCK - SX_CARRY);
const DECK_TOP = GROUND + TRUCK.deckY;
const P_LOWER = ik(SX_CARRY, TIP_CARRY_X, DECK_TOP - C40.h - STACKER.hang);
const P_RELEASE = ik(SX_CARRY, TIP_CARRY_X, DECK_TOP - C40.h - STACKER.hang - 90);
const TRUCK_X_LOAD = TIP_CARRY_X - TRUCK.slotX;

type Rig = {
  sx: number;
  a: number;
  ext: number;
  attach: number; // 0 = on stack, 1 = on spreader, 2 = on trailer
  tilt: number;
  click: number;
  tx: number; // truck x
  zoom: number;
  focus: number; // 0 = scene centre, 1 = follow the white container
  dock: number; // 0 = ground where it is, 1 = ground on the dark panel's top edge
};

export function Scene3Yard() {
  const root = useRef<HTMLElement>(null);

  useGSAP(
    () => {
      const el = root.current!;
      const q = (s: string) => el.querySelector<SVGGraphicsElement>(`[data-part="${s}"]`)!;
      const svg = el.querySelector<SVGSVGElement>("[data-yard-svg]")!;
      const cam = el.querySelector<SVGGElement>("[data-cam]")!;
      const parts = {
        stacker: q("stacker"),
        boom: q("boom"),
        inner: q("inner"),
        cylinder: q("cylinder") as unknown as SVGLineElement,
        spreader: q("spreader"),
        white: q("white"),
        truck: q("truck"),
        stackerWheels: [...el.querySelectorAll<SVGGElement>('[data-part="stacker"] [data-part="wheel"]')],
        truckWheels: [...el.querySelectorAll<SVGGElement>('[data-part="truck-wheel"]')],
      };

      const rig: Rig = { sx: -760, ...P_REST, attach: 0, tilt: 0, click: 0, tx: 1900, zoom: 1, focus: 0, dock: 0 };
      let baseZoom = 1;
      let dockShift = 0;

      // Where (in viewBox units) the ground must sit so it lines up with the panel edge at 40% of the screen.
      const measure = () => {
        const aspect = window.innerWidth / window.innerHeight;
        baseZoom = aspect < 0.9 ? 2.2 : aspect < 1.3 ? 1.35 : 1;
        const ctm = svg.getScreenCTM();
        const rect = svg.getBoundingClientRect();
        if (!ctm) return;
        const targetView = (rect.top + rect.height * 0.4 - ctm.f) / ctm.d;
        dockShift = targetView - GROUND;
      };

      const render = () => {
        const { sx, a, ext } = rig;
        const L = STACKER.boomLength + ext;
        const px = sx + STACKER.pivot.x;
        const py = GROUND + STACKER.pivot.y;
        const tipX = px + Math.cos(a * DEG) * L;
        const tipY = py - Math.sin(a * DEG) * L;

        parts.stacker.setAttribute("transform", `translate(${sx} ${GROUND})`);
        parts.boom.setAttribute("transform", `translate(${STACKER.pivot.x} ${STACKER.pivot.y}) rotate(${-a})`);
        parts.inner.setAttribute("transform", `translate(${ext} 0)`);
        const cyl = STACKER.cylinderAt;
        parts.cylinder.setAttribute("x2", String(STACKER.pivot.x + Math.cos(a * DEG) * cyl));
        parts.cylinder.setAttribute("y2", String(STACKER.pivot.y - Math.sin(a * DEG) * cyl));
        parts.spreader.setAttribute("transform", `translate(${tipX - sx} ${tipY - GROUND})`);

        const wheelDeg = (sx / STACKER.wheelR[1]) * (180 / Math.PI);
        parts.stackerWheels.forEach((w) => w.setAttribute("transform", `rotate(${wheelDeg})`));
        parts.truck.setAttribute("transform", `translate(${rig.tx} ${GROUND})`);
        const truckDeg = (rig.tx / TRUCK.wheelR) * (180 / Math.PI);
        parts.truckWheels.forEach((w) => w.setAttribute("transform", `rotate(${truckDeg})`));

        // Container follows whichever carrier currently holds it.
        let cx: number;
        let cy: number;
        if (rig.attach < 0.5) {
          cx = WHITE_REST.x;
          cy = WHITE_REST.y;
        } else if (rig.attach < 1.5) {
          cx = tipX - C40.w / 2;
          cy = tipY + STACKER.hang;
        } else {
          cx = rig.tx + TRUCK.slotX - C40.w / 2;
          cy = DECK_TOP - C40.h;
        }
        const s = 1 + rig.click * 0.03;
        parts.white.setAttribute(
          "transform",
          `translate(${cx} ${cy}) rotate(${rig.tilt} ${C40.w / 2} 0) translate(${(C40.w * (1 - s)) / 2} 0) scale(${s})`,
        );

        // Camera: zoom around a focus that blends from scene centre to the container.
        const fx = gsap.utils.interpolate(VB.w / 2, cx + C40.w / 2, rig.focus);
        const fy = gsap.utils.interpolate(VB.h / 2, cy + C40.h / 2, rig.focus);
        const z = baseZoom * rig.zoom;
        // Undocked: focus point at the viewBox centre. Docked: ground line pinned to the panel's top edge.
        const undockedY = VB.h / 2 - fy * z;
        const dockedY = GROUND + dockShift - GROUND * z;
        const camY = gsap.utils.interpolate(undockedY, dockedY, rig.dock);
        cam.setAttribute("transform", `translate(${VB.w / 2 - fx * z} ${camY}) scale(${z})`);
      };

      const mm = gsap.matchMedia();

      mm.add({ motion: MQ.motion, mobile: MQ.mobile }, (ctx) => {
        if (!ctx.conditions?.motion) return;
        measure();
        const ghost = buildGhostTl(el.querySelector<HTMLElement>("[data-panel] [data-ghost-heading]")!);
        ghost.tl.pause(0);

        const tl = gsap.timeline({
          defaults: { ease: "power1.inOut" },
          onUpdate: render,
          scrollTrigger: {
            trigger: el,
            start: "top top",
            end: ctx.conditions.mobile ? "+=520%" : "+=720%",
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

        // 1 · stacker drives in
        tl.to(rig, { sx: SX_LOCK, duration: 1.4, ease: "power2.out" }, 0)
          .fromTo("[data-yard-eyebrow]", { autoAlpha: 0, y: 20 }, { autoAlpha: 1, y: 0, duration: 0.5 }, 0.3)
          // 2 · boom reaches, spreader locks
          .to(rig, { a: P_LOCK.a, ext: P_LOCK.ext, duration: 1.4 }, 1.5)
          .set(rig, { attach: 1 }, 2.9)
          .to(rig, { click: 1, duration: 0.12, yoyo: true, repeat: 1, ease: "power2.out" }, 2.9)
          // 3 · lift, camera pushes in
          .to(rig, { a: P_LIFT.a, ext: P_LIFT.ext, duration: 1.4 }, 3.3)
          .fromTo(rig, { tilt: 0 }, { tilt: -2.5, duration: 0.6, ease: "sine.out" }, 3.3)
          .to(rig, { tilt: 0, duration: 0.8, ease: "sine.inOut" }, 3.9)
          .to(rig, { zoom: 1.6, focus: 1, duration: 1.6 }, 3.4)
          .to("[data-yard-eyebrow]", { autoAlpha: 0, duration: 0.4 }, 3.4)
          // 4 · back off the stack, truck reverses in, lower, release, stacker leaves
          .to(rig, { sx: SX_CARRY, a: P_CARRY.a, ext: P_CARRY.ext, duration: 1.4 }, 5)
          .to(rig, { tx: TRUCK_X_LOAD, duration: 1.8, ease: "power2.out" }, 5.4)
          .to(rig, { a: P_LOWER.a, ext: P_LOWER.ext, duration: 1 }, 7.2)
          .set(rig, { attach: 2 }, 8.2)
          .to(rig, { a: P_RELEASE.a, ext: P_RELEASE.ext, duration: 0.6 }, 8.25)
          .to(rig, { sx: -1300, a: 38, ext: 0, duration: 1.6, ease: "power2.in" }, 8.8)
          // 5 · camera pulls back, dark panel rises; truck sits on its edge
          .to(rig, { zoom: 1, focus: 0, duration: 1.4 }, 8.9)
          .to(rig, { dock: 1, duration: 1.4 }, 9.6)
          .to("[data-stack]", { autoAlpha: 0, duration: 0.8 }, 9.4)
          .fromTo("[data-panel]", { clipPath: "inset(100% 0 0 0)" }, { clipPath: "inset(40% 0 0 0)", duration: 1.4 }, 9.6)
          // SCENE 4 · services inside the panel; truck creeps right
          .to(rig, { tx: TRUCK_X_LOAD + 260, duration: 5, ease: "none" }, 11)
          .fromTo("[data-panel] [data-track]", { xPercent: 0 }, { xPercent: -18, duration: 7, ease: "none" }, 10)
          .add(ghost.tl.tweenFromTo(0, ghost.tl.duration(), { duration: 1.4, ease: "none" }), 11.1)
          .fromTo("[data-services-copy] p", { autoAlpha: 0, y: 24 }, { autoAlpha: 1, y: 0, stagger: 0.2, duration: 0.8 }, 11.6)
          .fromTo(
            "[data-service-col]",
            { autoAlpha: 0, y: 40 },
            { autoAlpha: 1, y: 0, stagger: 0.18, duration: 0.9, ease: "power2.out" },
            12.3,
          )
          .fromTo("[data-service-ghost]", { autoAlpha: 0.5, y: -14, x: 6 }, { autoAlpha: 0, y: 0, x: 0, stagger: 0.18, duration: 1 }, 12.3)
          .fromTo("[data-services-cta]", { autoAlpha: 0 }, { autoAlpha: 1, duration: 0.5 }, 13.6)
          // exit · truck accelerates off, panel takes the whole screen
          .to(rig, { tx: 2600, duration: 1.6, ease: "power3.in" }, 16)
          .to("[data-panel]", { clipPath: "inset(0% 0 0 0)", duration: 1.4 }, 16.3)
          .to("[data-panel-content]", { autoAlpha: 0, y: -40, duration: 0.8 }, 16.6);

        render();
        return () => ghost.restore();
      });

      // Reduced motion: final, readable state — truck loaded, services visible, no pin.
      mm.add(MQ.reduced, () => {
        Object.assign(rig, { sx: -1300, attach: 2, tx: TRUCK_X_LOAD, zoom: 1, focus: 0, dock: 0 });
        render();
        el.classList.add("is-static");
        gsap.set("[data-panel]", { clipPath: "none" });
        return () => el.classList.remove("is-static");
      });
    },
    { scope: root },
  );

  return (
    <section
      ref={root}
      data-scene="yard"
      data-nav-theme="light"
      className="group/yard relative h-[100svh] overflow-hidden bg-paper [&.is-static]:h-auto"
      aria-labelledby="services-title"
    >
      {/* Yard stage */}
      <div className="absolute inset-0 group-[.is-static]/yard:relative group-[.is-static]/yard:h-[60vh]">
        <p data-yard-eyebrow className="eyebrow absolute left-gutter top-[22%] z-10 text-steel opacity-0 md:left-[clamp(140px,13vw,220px)]">
          Container yard · ICD Tughlakabad
        </p>
        <svg
          data-yard-svg
          viewBox={`0 0 ${VB.w} ${VB.h}`}
          preserveAspectRatio="xMidYMid meet"
          className="absolute inset-0 h-full w-full overflow-visible"
          aria-hidden
        >
          <defs>
            <linearGradient id="ctr-shade" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0" stopColor="#fff" stopOpacity="0.12" />
              <stop offset="1" stopColor="#000" stopOpacity="0.14" />
            </linearGradient>
          </defs>
          <g data-cam>
            <line x1={-4000} x2={5600} y1={GROUND} y2={GROUND} stroke="#0a0d14" strokeOpacity={0.18} strokeWidth={1.5} />
            {[...Array(40)].map((_, i) => (
              <line key={i} x1={-2000 + i * 160} x2={-1940 + i * 160} y1={GROUND + 16} y2={GROUND + 16} stroke="#0a0d14" strokeOpacity={0.08} strokeWidth={2} />
            ))}
            {/* Stack */}
            <g data-stack>
            <Container w={C20.w} h={C20.h} color="#1b2c54" rib="#2c4278" label="RLWU 204118" x={STACK_X} y={GROUND - C20.h} />
            <Container w={C20.w} h={C20.h} color="#e85d24" rib="#c2481a" label="RLWU 377051" x={STACK_X + C20.w + 5} y={GROUND - C20.h} />
            <Container w={C20.w} h={C20.h} color="#128080" rib="#0d6464" label="RLWU 118602" x={STACK_X + 2 * (C20.w + 5) + 40} y={GROUND - C20.h} />
            </g>
            <ReachStacker />
            <TruckSide />
            <Container part="white" w={C40.w} h={C40.h} color="#e9ebee" rib="#c3c8cf" label="RLWU 540227 · 40'" />
          </g>
        </svg>
      </div>

      {/* Dark panel: the road the truck sits on, and the services section inside it */}
      <div
        data-panel
        data-nav-theme="dark"
        className="absolute inset-0 bg-ink text-paper group-[.is-static]/yard:relative"
        style={{ clipPath: "inset(100% 0 0 0)" }}
      >
        <div data-panel-content className="absolute inset-x-0 bottom-0 top-[40%] flex flex-col justify-center gap-[3.5vh] px-gutter py-[4vh] group-[.is-static]/yard:relative group-[.is-static]/yard:top-0 group-[.is-static]/yard:py-24 md:pl-[clamp(140px,13vw,220px)]">
          <Marquee text={servicesSection.watermark} drive="external" className="absolute inset-x-0 top-[8%] text-paper/[0.05]" />
          <div className="relative grid items-end gap-6 md:grid-cols-12">
            <div className="md:col-span-7">
              <p className="eyebrow mb-4 text-brand">{servicesSection.eyebrow}</p>
              <GhostHeading
                as="h2"
                lines={servicesSection.headline}
                mutedLines={[2]}
                mode="external"
                className="text-[clamp(1.9rem,3.6vw,3.6rem)] leading-[0.92]"
              />
              <span id="services-title" className="sr-only">
                {servicesSection.headline.join(" ")}
              </span>
            </div>
            <div data-services-copy className="flex max-w-[44ch] flex-col gap-3 text-[14px] leading-relaxed text-paper/65 md:col-span-5">
              {servicesSection.paragraphs.map((p) => (
                <p key={p}>{p}</p>
              ))}
            </div>
          </div>

          <ul className="relative -mx-gutter flex snap-x snap-mandatory gap-px overflow-x-auto px-gutter pb-2 md:mx-0 md:grid md:grid-cols-6 md:overflow-visible md:px-0">
            {homeServices.map((s, i) => (
              <li key={s.title} data-service-col className="min-w-[68%] snap-start border-t border-paper/15 pt-4 sm:min-w-[40%] md:min-w-0">
                <Link href={s.href} className="group relative block pr-4">
                  <span className="ui-label mb-3 block text-steel">{String(i + 1).padStart(2, "0")}</span>
                  <span className="relative mb-3 block h-7 w-7">
                    <Icon name={s.icon} className="absolute inset-0 h-7 w-7 text-paper transition-transform duration-500 ease-expo group-hover:translate-x-1" />
                    <span data-service-ghost aria-hidden className="absolute inset-0">
                      <Icon name={s.icon} className="h-7 w-7 text-paper/60" />
                    </span>
                  </span>
                  <span className="display block text-[15px] leading-[1.05]">{s.title}</span>
                  <span className="mt-2 block text-[12.5px] leading-snug text-paper/55">{s.body}</span>
                  <span aria-hidden className="mt-3 block h-px w-0 bg-brand transition-all duration-500 ease-expo group-hover:w-10" />
                </Link>
              </li>
            ))}
          </ul>
          <div data-services-cta className="relative">
            <Button href={servicesSection.cta.href} variant="ghost" tone="dark">
              {servicesSection.cta.label}
            </Button>
          </div>
        </div>
      </div>
    </section>
  );
}
