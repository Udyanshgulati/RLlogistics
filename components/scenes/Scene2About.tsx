"use client";

import Link from "next/link";
import { useRef } from "react";
import { gsap, useGSAP, MQ } from "@/lib/gsap";
import { about } from "@/content/home";
import { stats } from "@/content/stats";
import { RevealHeading } from "@/components/text/RevealHeading";
import { ScrubParagraph } from "@/components/text/ScrubParagraph";
import { Counter } from "@/components/text/Counter";
import { AerialRoad } from "@/components/illustrations/AerialRoad";
import { Arrow } from "@/components/ui/Button";
import type { AssetOverrides } from "@/lib/asset-slots";

/**
 * SCENE 2 — "We move freight. We own the outcome."
 * Paper section that receives the dive's white haze. The left column (image card + headline) stays sticky
 * while the copy and counters scroll past on the right; the image card parallaxes at its own speed.
 */
export function Scene2About({ assets = {} }: { assets?: AssetOverrides }) {
  const root = useRef<HTMLElement>(null);

  useGSAP(
    () => {
      const mm = gsap.matchMedia();
      mm.add(MQ.motion, () => {
        gsap.fromTo(
          "[data-about-card]",
          { yPercent: 30 },
          { yPercent: -40, ease: "none", scrollTrigger: { trigger: root.current, start: "top bottom", end: "bottom top", scrub: true } },
        );
        // Headline lines go steel → ink as you read down the section.
        gsap.fromTo(
          "[data-about-heading] .block",
          { color: "rgb(138 143 152 / 0.45)" },
          {
            color: "rgb(10 13 20)",
            stagger: 0.3,
            ease: "none",
            scrollTrigger: { trigger: root.current, start: "top 60%", end: "center 45%", scrub: true },
          },
        );
      });
    },
    { scope: root },
  );

  return (
    <section ref={root} data-nav-theme="light" data-scene="about" className="relative bg-paper text-ink" aria-labelledby="about-title">
      <div className="grid gap-16 px-gutter pb-[18vh] pt-[14vh] md:grid-cols-12 md:pl-[clamp(140px,13vw,220px)]">
        <div className="md:sticky md:top-0 md:col-span-7 md:flex md:h-screen md:flex-col md:justify-between md:py-[12vh]">
          <div data-about-card className="relative aspect-[2/1] w-[min(340px,70%)] overflow-hidden rounded-[2px]">
            {assets.heroRoad ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={assets.heroRoad} alt={about.imageAlt} className="h-full w-full object-cover" />
            ) : (
              <AerialRoad className="h-full w-full" />
            )}
            <span className="ui-label absolute bottom-2 left-2 text-paper/80">NH-48 · Gurugram</span>
          </div>
          <div className="mt-16 md:mt-0">
            <p className="eyebrow mb-5 text-steel">{about.eyebrow}</p>
            <div data-about-heading id="about-title">
              <RevealHeading lines={about.headline} className="text-[clamp(2.3rem,4.6vw,5.2rem)] leading-[0.9] tracking-[-0.02em]" />
            </div>
          </div>
        </div>

        <div className="flex flex-col gap-24 md:col-span-5 md:pt-[30vh]">
          <div className="flex max-w-[40ch] flex-col gap-6">
            {about.paragraphs.map((p) => (
              <ScrubParagraph key={p} className="text-[clamp(1.05rem,1.35vw,1.3rem)] leading-snug">
                {p}
              </ScrubParagraph>
            ))}
            <Link href={about.link.href} className="ui-label group inline-flex items-center gap-2 border-b border-ink/20 pb-1 hover:border-brand hover:text-brand">
              {about.link.label}
              <Arrow className="transition-transform group-hover:translate-x-1" />
            </Link>
          </div>

          <dl className="flex flex-col divide-y divide-ink/10 border-y border-ink/10">
            {stats.map((s, i) => (
              <div key={s.label} className="grid grid-cols-[auto_1fr] items-end gap-x-6 py-8">
                <dt className="ui-label col-span-2 mb-3 text-steel">{String(i + 1).padStart(2, "0")}</dt>
                <dd className="col-span-2">
                  <Counter value={s.value} decimals={s.decimals} suffix={s.suffix} className="text-[clamp(3rem,6.2vw,6rem)]" />
                </dd>
                <dd className="col-span-2 mt-3 text-[15px]">{s.label}</dd>
                <dd className="col-span-2 text-[13px] text-steel">{s.sub}</dd>
              </div>
            ))}
          </dl>
        </div>
      </div>
    </section>
  );
}
