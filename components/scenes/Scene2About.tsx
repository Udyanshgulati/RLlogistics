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
      // Card parallax only on desktop: on phones the card sits in the flow above the headline and must not drift over it.
      mm.add(`${MQ.motion} and ${MQ.desktop}`, () => {
        gsap.fromTo(
          "[data-about-card]",
          { yPercent: 30 },
          { yPercent: -40, ease: "none", scrollTrigger: { trigger: root.current, start: "top bottom", end: "bottom top", scrub: true } },
        );
      });
      mm.add(MQ.motion, () => {
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
    <section ref={root} data-nav-theme="light" data-scene="about" className="relative z-10 -mt-[50svh] bg-[linear-gradient(to_bottom,rgb(var(--paper)/0),rgb(var(--paper))_25svh)] pt-[25svh] text-ink motion-reduce:mt-0 motion-reduce:bg-paper motion-reduce:pt-0" aria-labelledby="about-title">
      <div className="grid gap-10 px-gutter pb-[12vh] pt-[4vh] lg:grid-cols-12 lg:gap-16 lg:pb-[18vh] lg:pl-[clamp(140px,13vw,220px)]">
        <div className="lg:sticky lg:top-0 lg:col-span-7 lg:flex lg:h-screen lg:flex-col lg:justify-between lg:py-[12vh]">
          <div data-about-card className="relative aspect-[16/9] w-full overflow-hidden rounded-[2px] sm:w-[min(420px,70%)] lg:aspect-[2/1] lg:w-[min(340px,70%)]">
            {assets.heroRoad ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={assets.heroRoad} alt={about.imageAlt} className="h-full w-full object-cover" />
            ) : (
              <AerialRoad className="h-full w-full" />
            )}
            <span className="ui-label absolute bottom-2 left-2 text-paper/80">NH-48 · Gurugram</span>
          </div>
          <div className="mt-10 lg:mt-0">
            <p className="eyebrow mb-5 text-steel">{about.eyebrow}</p>
            <div data-about-heading id="about-title">
              <RevealHeading lines={about.headline} className="text-[clamp(2rem,9vw,5.5rem)] leading-[0.95] tracking-[-0.02em] lg:text-[clamp(2.3rem,4.6vw,5.2rem)] lg:leading-[0.9]" />
            </div>
          </div>
        </div>

        <div className="flex flex-col gap-12 lg:col-span-5 lg:gap-24 lg:pt-[30vh]">
          <div className="flex max-w-[40ch] flex-col gap-6">
            {about.paragraphs.map((p) => (
              <ScrubParagraph key={p} className="text-[clamp(1.05rem,1.35vw,1.3rem)] leading-snug">
                {p}
              </ScrubParagraph>
            ))}
            <Link href={about.link.href} className="ui-label group inline-flex min-h-11 items-center gap-2 border-b max-lg:self-start border-ink/20 pb-1 hover:border-brand hover:text-brand lg:min-h-0">
              {about.link.label}
              <Arrow className="transition-transform group-hover:translate-x-1" />
            </Link>
          </div>

          <dl className="flex flex-col divide-y divide-ink/10 border-y border-ink/10 sm:max-lg:grid sm:max-lg:grid-cols-2 sm:max-lg:divide-y-0">
            {stats.map((s, i) => (
              <div key={s.label} className="grid grid-cols-[auto_1fr] items-center gap-x-5 py-5 lg:items-end lg:gap-x-6 lg:py-8">
                <dt className="ui-label col-span-2 mb-3 text-steel max-lg:sr-only">{String(i + 1).padStart(2, "0")}</dt>
                {/* Phones: number left, label right. Desktop: stacked. */}
                <dd className="row-span-2 lg:col-span-2 lg:row-span-1">
                  <Counter value={s.value} decimals={s.decimals} suffix={s.suffix} className="text-[clamp(2.5rem,12vw,4rem)] lg:text-[clamp(3rem,6.2vw,6rem)]" />
                </dd>
                <dd className="self-end text-[15px] max-lg:leading-snug lg:col-span-2 lg:mt-3">{s.label}</dd>
                <dd className="self-start text-[14px] text-steel max-lg:leading-snug lg:col-span-2 lg:text-[13px]">{s.sub}</dd>
              </div>
            ))}
          </dl>
        </div>
      </div>
    </section>
  );
}
