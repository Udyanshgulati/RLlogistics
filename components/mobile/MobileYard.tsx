"use client";

import { useRef } from "react";
import { gsap, useGSAP } from "@/lib/gsap";
import { ReachStacker, STACKER } from "@/components/illustrations/ReachStacker";
import { ShadingDefs } from "@/components/illustrations/Shading";
import { TruckSide, TRUCK } from "@/components/illustrations/TruckSide";
import { Container } from "@/components/illustrations/Container";
import { mobileCopy, mobileHeadlines } from "@/content/mobile";
import { MEyebrow, MHeading } from "./ui";
import { M_MOTION } from "./motion";

// Same world units and real proportions as the desktop yard (34 px/m).
const GROUND = 760;
const C40 = { w: 410, h: 88 };
const DEG = Math.PI / 180;
const S = STACKER.scale;
const HANG = STACKER.hang * S;
const SX = 200; // stacker parks here
const TIP_X = 750; // container centre while it is carried
const DECK_TOP = GROUND + TRUCK.deckY;
const TRUCK_X_LOAD = TIP_X - TRUCK.slotX;
const TRUCK_X_OUT = 1260; // rear of the truck past the right edge of the frame

type Pose = { a: number; ext: number };
function ik(sx: number, tx: number, ty: number): Pose {
  const px = sx + STACKER.pivot.x * S;
  const py = GROUND + STACKER.pivot.y * S;
  const dx = tx - px;
  const dy = py - ty;
  return { a: Math.atan2(dy, dx) / DEG, ext: Math.hypot(dx, dy) / S - STACKER.boomLength };
}
const P_REST: Pose = { a: 30, ext: 0 };
const P_GROUND = ik(SX, TIP_X, GROUND - C40.h - HANG);
// High enough for the container to clear the cab's roof fairing (≈4.3 m) as the truck reverses under it.
const P_UP = ik(SX, TIP_X, GROUND - 165 - C40.h - HANG);
const P_LOWER = ik(SX, TIP_X, DECK_TOP - C40.h - HANG);
const P_RELEASE = ik(SX, TIP_X, DECK_TOP - C40.h - HANG - 45);

/**
 * Phone yard scene (pinned, ~200svh): the reach stacker lifts the container, it swings, lands on the
 * truck, and the truck drives out to the right. One scrubbed timeline drives a few numbers; render()
 * places the SVG parts from them, so it plays forwards and backwards exactly.
 */
export function MobileYard({ assets = {} }: { assets?: { stackerBody?: string; stackerBoom?: string; truckSide?: string; containerWhite?: string } }) {
  const root = useRef<HTMLElement>(null);

  useGSAP(
    () => {
      const el = root.current!;
      const q = (p: string) => el.querySelector(`[data-part="${p}"]`) as SVGGElement;
      const parts = {
        stacker: q("stacker"),
        boom: q("boom"),
        inner: q("inner"),
        spreader: q("spreader"),
        cylinder: q("cylinder") as unknown as SVGLineElement,
        barrel: q("cylinder-barrel") as unknown as SVGLineElement,
        white: q("white"),
        truck: q("truck"),
        stackerWheels: [...el.querySelectorAll<SVGGElement>('[data-part="stacker"] [data-part="wheel"]')],
        truckWheels: [...el.querySelectorAll<SVGGElement>('[data-part="truck-wheel"]')],
      };
      // Final, readable state (also what reduced motion and the first paint show): container on the truck.
      const rig = { sx: SX, ...P_RELEASE, attach: 2, tilt: 0, tx: TRUCK_X_LOAD, squash: 0 };

      const render = () => {
        const { sx, a, ext } = rig;
        const L = (STACKER.boomLength + ext) * S;
        const tipX = sx + STACKER.pivot.x * S + Math.cos(a * DEG) * L;
        const tipY = GROUND + STACKER.pivot.y * S - Math.sin(a * DEG) * L;
        parts.stacker.setAttribute("transform", `translate(${sx} ${GROUND}) scale(${S})`);
        parts.boom.setAttribute("transform", `translate(${STACKER.pivot.x} ${STACKER.pivot.y}) rotate(${-a})`);
        parts.inner.setAttribute("transform", `translate(${ext} 0)`);
        const rodX = STACKER.pivot.x + Math.cos(a * DEG) * STACKER.cylinderAt;
        const rodY = STACKER.pivot.y - Math.sin(a * DEG) * STACKER.cylinderAt;
        parts.cylinder.setAttribute("x2", String(rodX));
        parts.cylinder.setAttribute("y2", String(rodY));
        const { x: bx, y: by } = STACKER.cylinderBase;
        const len = Math.hypot(rodX - bx, rodY - by) || 1;
        parts.barrel.setAttribute("x2", String(bx + ((rodX - bx) / len) * 70));
        parts.barrel.setAttribute("y2", String(by + ((rodY - by) / len) * 70));
        parts.spreader.setAttribute("transform", `translate(${(tipX - sx) / S} ${(tipY - GROUND) / S})`);
        const wheelDeg = (sx / (STACKER.wheelR[1] * S)) * (180 / Math.PI);
        parts.stackerWheels.forEach((w) => w.setAttribute("transform", `rotate(${wheelDeg})`));
        parts.truck.setAttribute("transform", `translate(${rig.tx} ${GROUND + rig.squash})`);
        const truckDeg = (rig.tx / TRUCK.wheelR) * (180 / Math.PI);
        parts.truckWheels.forEach((w) => w.setAttribute("transform", `rotate(${truckDeg})`));

        let cx: number, cy: number;
        if (rig.attach < 0.5) {
          cx = TIP_X - C40.w / 2;
          cy = GROUND - C40.h;
        } else if (rig.attach < 1.5) {
          cx = tipX - C40.w / 2;
          cy = tipY + HANG;
        } else {
          cx = rig.tx + TRUCK.slotX - C40.w / 2;
          cy = DECK_TOP - C40.h + rig.squash;
        }
        parts.white.setAttribute("transform", `translate(${cx} ${cy}) rotate(${rig.tilt} ${C40.w / 2} 0)`);
      };
      render();

      const mm = gsap.matchMedia();
      mm.add(M_MOTION, () => {
        Object.assign(rig, { sx: -520, ...P_REST, attach: 0, tilt: 0, tx: 1500, squash: 0 });
        render();
        const tl = gsap.timeline({
          defaults: { ease: "power1.inOut" },
          onUpdate: render,
          scrollTrigger: { trigger: el, start: "top top", end: "+=200%", pin: true, scrub: 1, anticipatePin: 1, refreshPriority: 1 },
        });
        tl.to(rig, { sx: SX, duration: 1, ease: "power2.out" }, 0)
          .to(rig, { a: P_GROUND.a, ext: P_GROUND.ext, duration: 0.6 }, 0.9)
          .set(rig, { attach: 1 }, 1.5)
          // lift, with a damped swing of the load
          .to(rig, { a: P_UP.a, ext: P_UP.ext, duration: 1.2 }, 1.6)
          .fromTo(rig, { tilt: 0 }, { tilt: -2.2, duration: 0.4, ease: "sine.out" }, 1.6)
          .to(rig, { tilt: 1.1, duration: 0.45, ease: "sine.inOut" }, 2.0)
          .to(rig, { tilt: -0.4, duration: 0.4, ease: "sine.inOut" }, 2.45)
          .to(rig, { tilt: 0, duration: 0.35, ease: "sine.inOut" }, 2.85)
          // truck reverses in under the load
          .to(rig, { tx: TRUCK_X_LOAD, duration: 1.4, ease: "power2.out" }, 2.6)
          // lower, land (suspension takes the weight), release
          .to(rig, { a: P_LOWER.a, ext: P_LOWER.ext, duration: 0.8 }, 4.1)
          .set(rig, { attach: 2 }, 4.9)
          .to(rig, { squash: 2, duration: 0.1, ease: "power2.out" }, 4.9)
          .to(rig, { squash: 0, duration: 0.4, ease: "elastic.out(1, 0.4)" }, 5.0)
          .to(rig, { a: P_RELEASE.a, ext: P_RELEASE.ext, duration: 0.4 }, 5.0)
          // truck drives out to the right; boom settles
          .to(rig, { tx: TRUCK_X_OUT, duration: 1.4, ease: "power2.in" }, 5.4)
          .to(rig, { a: P_REST.a, ext: 0, duration: 1 }, 5.6);
        return () => {
          Object.assign(rig, { sx: SX, ...P_RELEASE, attach: 2, tilt: 0, tx: TRUCK_X_LOAD, squash: 0 });
          render();
        };
      });
    },
    { scope: root },
  );

  return (
    <section ref={root} data-nav-theme="light" className="relative flex min-h-[100svh] flex-col justify-center overflow-hidden bg-paper px-5 pb-12 pt-24 text-ink">
      <MEyebrow className="mb-5 text-steel">Container yard · ICD Tughlakabad</MEyebrow>
      <MHeading lines={mobileHeadlines.yard} muted={[2]} />
      <p className="mt-5 text-[16px] leading-[1.55] text-ink/70">{mobileCopy.yard}</p>
      {/* Scene: frames the stacker and the 17.6 m rig at their real proportions, ~92vw wide. */}
      <svg viewBox="140 420 1060 390" className="mx-auto mt-10 block w-[92vw] max-w-none overflow-visible" aria-hidden>
        <ShadingDefs />
        <defs>
          <linearGradient id="ctr-dirt" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0.55" stopColor="#5a4632" stopOpacity="0" />
            <stop offset="1" stopColor="#5a4632" stopOpacity="0.18" />
          </linearGradient>
        </defs>
        <line x1={-400} x2={1800} y1={GROUND} y2={GROUND} stroke="#0a0d14" strokeOpacity={0.2} strokeWidth={2} />
        {[...Array(14)].map((_, i) => (
          <line key={i} x1={i * 110 - 40} x2={i * 110 + 20} y1={GROUND + 18} y2={GROUND + 18} stroke="#0a0d14" strokeOpacity={0.1} strokeWidth={3} />
        ))}
        <ReachStacker bodyImg={assets.stackerBody} boomImg={assets.stackerBoom} />
        <TruckSide img={assets.truckSide} />
        <Container part="white" img={assets.containerWhite} w={C40.w} h={C40.h} color="#e9ebee" rib="#c3c8cf" label="RLWU 540227 · 40'" />
      </svg>
    </section>
  );
}
