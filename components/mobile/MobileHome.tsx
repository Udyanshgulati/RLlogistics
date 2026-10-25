"use client";

import Link from "next/link";
import { useRef, useState } from "react";
import { gsap, ScrollTrigger, useGSAP, EXPO } from "@/lib/gsap";
import { about, ocean, road, servicesSection, trust, cta } from "@/content/home";
import { mobileCopy, mobileHeadlines, mobileHero } from "@/content/mobile";
import { stats } from "@/content/stats";
import { homeServices } from "@/content/services";
import { testimonials, clientLogos } from "@/content/testimonials";
import { company } from "@/content/company";
import type { AssetOverrides } from "@/lib/asset-slots";
import { waterTexture, wakeTexture, whenNear } from "@/lib/textures";
import { Icon, type IconName } from "@/components/ui/Icon";
import { Avatar } from "@/components/ui/Avatar";
import { TruckTop } from "@/components/illustrations/TruckTop";
import { ShipTop } from "@/components/illustrations/ShipTop";
import { PlaneTop } from "@/components/illustrations/PlaneTop";
import { MobileGlobe } from "./MobileGlobe";
import { MobileYard } from "./MobileYard";
import { MButton, MDots, MEyebrow, MHeading, snapIndex } from "./ui";
import { M_MOTION } from "./motion";

const fmt = (v: number, d = 0) => v.toLocaleString("en-US", { minimumFractionDigits: d, maximumFractionDigits: d });

/**
 * Dedicated phone/tablet home (< 1024px). Normal document flow, one 20px gutter, one type scale.
 * Only two scrubbed pins (hero dive, yard); everything else is a fade-up reveal or native swipe.
 */
export function MobileHome({ assets = {} }: { assets?: AssetOverrides }) {
  const root = useRef<HTMLDivElement>(null);

  useGSAP(
    () => {
      const el = root.current!;
      const mm = gsap.matchMedia();
      mm.add(M_MOTION, () => {
        // Reveals: fade-up 24px, expo-out, at top 85%, reversing on the way back up.
        // Hero copy plays in on load (it's on screen from the start); everything else reveals on scroll.
        gsap.fromTo(
          "[data-m-hero] [data-m-reveal], [data-m-hero] [data-m-intro]",
          { autoAlpha: 0, y: 24 },
          { autoAlpha: 1, y: 0, duration: 0.8, ease: EXPO, stagger: 0.08, delay: 0.15 },
        );
        gsap.utils.toArray<HTMLElement>("[data-m-reveal]").filter((t) => !t.closest("[data-m-hero]")).forEach((t) => {
          gsap.fromTo(
            t,
            { autoAlpha: 0, y: 24 },
            { autoAlpha: 1, y: 0, duration: 0.8, ease: EXPO, scrollTrigger: { trigger: t, start: "top 85%", toggleActions: "play none none reverse" } },
          );
        });

        // Count-up stats.
        gsap.utils.toArray<HTMLElement>("[data-m-count]").forEach((n) => {
          const value = Number(n.dataset.value);
          const d = Number(n.dataset.decimals || 0);
          const o = { v: 0 };
          gsap.to(o, {
            v: value,
            duration: 1.6,
            ease: "power3.out",
            onUpdate: () => (n.textContent = fmt(o.v, d)),
            scrollTrigger: { trigger: n, start: "top 85%", toggleActions: "restart none none reset" },
          });
        });

        // Hero dive (pinned once the whole hero is on screen): copy lifts, globe grows into blue, blue whitens to paper.
        const hero = el.querySelector<HTMLElement>("[data-m-hero]")!;
        gsap
          .timeline({
            defaults: { ease: "none" },
            scrollTrigger: {
              trigger: hero,
              start: "bottom bottom",
              end: "+=100%",
              pin: true,
              scrub: 1,
              anticipatePin: 1,
              onUpdate: (self) => hero.setAttribute("data-nav-theme", self.progress > 0.75 ? "light" : "dark"),
            },
          })
          .to("[data-m-hero-copy]", { autoAlpha: 0, y: -40, duration: 0.3 }, 0)
          .fromTo("[data-m-hero-globe]", { scale: 1 }, { scale: 2.6, duration: 0.7, ease: "power2.in" }, 0.05)
          .to("[data-m-hero-globe]", { autoAlpha: 0, duration: 0.25 }, 0.5)
          .fromTo("[data-m-atmos]", { autoAlpha: 0 }, { autoAlpha: 1, duration: 0.3 }, 0.35)
          .fromTo("[data-m-haze]", { autoAlpha: 0 }, { autoAlpha: 1, duration: 0.3 }, 0.7);

        // Road: the truck drives down the strip with scroll progress (transform only).
        const list = el.querySelector<HTMLElement>("[data-m-road]")!;
        gsap.fromTo(
          "[data-m-truck]",
          { y: 0 },
          {
            y: () => list.offsetHeight - 64,
            ease: "none",
            scrollTrigger: { trigger: list, start: "top 70%", end: "bottom 50%", scrub: 0.6, invalidateOnRefresh: true },
          },
        );

        // Ocean: ship glides up through the frame; plane crosses the sky band diagonally.
        gsap.fromTo("[data-m-ship]", { yPercent: 8 }, { yPercent: -8, ease: "none", scrollTrigger: { trigger: "[data-m-ocean]", start: "top bottom", end: "bottom top", scrub: true } });
        gsap.fromTo(
          "[data-m-plane]",
          { xPercent: -120, yPercent: 60, rotate: -28 },
          { xPercent: 160, yPercent: -90, rotate: -28, ease: "none", scrollTrigger: { trigger: "[data-m-sky]", start: "top bottom", end: "bottom top", scrub: true } },
        );

        // Debug aid for the mobile harness: only mobile triggers should exist here.
        (window as Window & { __M_TRIGGERS__?: () => number }).__M_TRIGGERS__ = () => ScrollTrigger.getAll().length;
      });

      // Ocean textures are generated only when the section gets close.
      const ocean = el.querySelector<HTMLElement>("[data-m-ocean]")!;
      return whenNear(ocean, [
        () => (ocean.querySelector<HTMLElement>("[data-m-water]")!.style.backgroundImage = `url(${waterTexture()})`),
        () => (ocean.querySelector<HTMLImageElement>("[data-m-wake]")!.src = wakeTexture()),
      ]);
    },
    { scope: root },
  );

  return (
    <div ref={root}>
      <Hero />
      <About heroRoad={assets.heroRoad} />
      <MobileYard assets={assets} />
      <Services />
      <Road truckTop={assets.truckTop} />
      <Ocean shipTop={assets.shipTop} />
      <section data-m-sky aria-hidden className="relative h-[60svh] overflow-hidden bg-[linear-gradient(to_bottom,#0a2a7a,#7ea2e6_45%,rgb(var(--paper)))]">
        <div data-m-plane className="absolute left-0 top-1/2 w-[78vw]">
          <PlaneTop src={assets.planeTop} className="h-auto w-full" />
        </div>
      </section>
      <Testimonials />
      <Cta />
    </div>
  );
}

function Hero() {
  return (
    <section data-m-hero data-nav-theme="dark" aria-label="Introduction" className="relative flex min-h-[100svh] flex-col overflow-hidden bg-ink text-paper">
      <div data-m-hero-globe className="relative mt-16 w-full">
        <MobileGlobe className="relative h-[min(55svh,100vw)] w-full" />
      </div>
      <div data-m-hero-copy className="relative z-10 flex flex-1 flex-col justify-end px-5 pb-[calc(32px+var(--safe-b))] pt-6">
        <div data-m-intro>
          <MEyebrow className="flex items-center gap-3 text-brand">
            <span aria-hidden className="h-px w-6 bg-brand" />
            {mobileHero.eyebrow}
          </MEyebrow>
        </div>
        <MHeading as="h1" lines={mobileHero.headline} className="mt-5" />
        <p data-m-intro className="mt-5 text-[16px] leading-[1.55] text-paper/75">
          {mobileHero.sub}
        </p>
        <div className="mt-8 flex flex-col gap-3">
          <MButton href="/services" variant="light">
            Our services
          </MButton>
          <MButton href="/contact#quote" variant="ghost-dark">
            Get a quote
          </MButton>
        </div>
      </div>
      <div
        data-m-atmos
        aria-hidden
        className="pointer-events-none invisible absolute inset-0 z-20 opacity-0"
        style={{
          background:
            "linear-gradient(to bottom, rgb(var(--ocean)) 0%, rgb(20 80 215) 14%, rgb(var(--ocean-bright)) 28%, rgb(88 142 255) 42%, rgb(150 185 255) 56%, rgb(200 216 250) 72%, rgb(var(--paper)) 88%)",
        }}
      >
        <div className="grain absolute inset-0 opacity-[0.05]" />
      </div>
      <div data-m-haze aria-hidden className="pointer-events-none invisible absolute inset-0 z-20 bg-paper opacity-0" />
    </section>
  );
}

function About({ heroRoad }: { heroRoad?: string }) {
  return (
    <section data-nav-theme="light" aria-labelledby="m-about" className="bg-paper px-5 py-24 text-ink">
      <div data-m-reveal className="relative aspect-[16/10] w-full overflow-hidden rounded-2xl bg-ink/10">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={heroRoad ?? "/assets/hero-road.jpg"} alt={about.imageAlt} loading="lazy" className="h-full w-full object-cover" />
        <span className="absolute bottom-3 left-3 font-mono text-[12px] uppercase tracking-[0.1em] text-paper/90">NH-48 · Gurugram</span>
      </div>
      <MEyebrow className="mt-12 text-steel">{about.eyebrow}</MEyebrow>
      <MHeading id="m-about" lines={mobileHeadlines.about} muted={[2, 3]} className="mt-5" />
      <p data-m-reveal className="mt-5 text-[16px] leading-[1.55] text-ink/75">
        {about.paragraphs[0]}
      </p>
      <MButton href={about.link.href} variant="ghost-light" className="mt-8">
        {about.link.label}
      </MButton>
      <dl className="mt-12 border-t border-ink/10">
        {stats.map((s) => (
          <div key={s.label} data-m-reveal className="flex items-center gap-5 border-b border-ink/10 py-5">
            <dt className="sr-only">{s.label}</dt>
            <dd className="display w-[42%] shrink-0 text-[48px] leading-none">
              <span data-m-count data-value={s.value} data-decimals={s.decimals ?? 0}>
                {fmt(s.value, s.decimals)}
              </span>
              <span className="text-brand">{s.suffix}</span>
            </dd>
            <dd className="min-w-0">
              <span className="block text-[16px] font-medium leading-snug">{s.label}</span>
              <span className="mt-1 block text-[14px] leading-snug text-ink/60">{s.sub}</span>
            </dd>
          </div>
        ))}
      </dl>
    </section>
  );
}

function Services() {
  const [active, setActive] = useState(0);
  return (
    <section data-nav-theme="dark" aria-labelledby="m-services" className="bg-ink py-24 text-paper">
      <div className="px-5">
        <MEyebrow className="text-brand">{servicesSection.eyebrow}</MEyebrow>
        <MHeading id="m-services" lines={mobileHeadlines.services} muted={[2]} className="mt-5" />
        <p data-m-reveal className="mt-5 text-[16px] leading-[1.55] text-paper/70">
          {mobileCopy.services}
        </p>
      </div>
      {/* Native touch carousel: scroll-snap, no GSAP. */}
      <ul
        data-m-reveal
        onScroll={(e) => setActive(snapIndex(e.currentTarget))}
        className="mt-8 flex snap-x snap-mandatory scroll-px-5 gap-4 overflow-x-auto overscroll-x-contain px-5 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      >
        {homeServices.map((s, i) => (
          <li key={s.title} className="w-[82vw] max-w-[420px] shrink-0 snap-start">
            <Link href={s.href} className="flex h-full flex-col rounded-2xl border border-paper/15 bg-paper/[0.04] p-5">
              <span className="font-mono text-[12px] uppercase tracking-[0.1em] text-steel">{String(i + 1).padStart(2, "0")}</span>
              <Icon name={s.icon} className="mt-4 h-8 w-8 text-paper" />
              <span className="mt-4 text-[18px] font-semibold leading-snug">{s.title}</span>
              <span className="mt-2 line-clamp-2 text-[16px] leading-[1.55] text-paper/65">{s.body}</span>
            </Link>
          </li>
        ))}
      </ul>
      <div className="px-5">
        <MDots count={homeServices.length} active={active} />
        <MButton href={servicesSection.cta.href} variant="ghost-dark" className="mt-8">
          {servicesSection.cta.label}
        </MButton>
      </div>
    </section>
  );
}

function Road({ truckTop }: { truckTop?: string }) {
  return (
    <section data-nav-theme="light" aria-labelledby="m-road" className="bg-paper px-5 py-24 text-ink">
      <MEyebrow className="text-steel">{road.eyebrow}</MEyebrow>
      <MHeading id="m-road" lines={mobileHeadlines.road} muted={[2]} className="mt-5" />
      <p data-m-reveal className="mt-5 text-[16px] leading-[1.55] text-ink/70">
        {mobileCopy.road}
      </p>
      <div data-m-road className="relative mt-10">
        {/* Road strip: 48px wide, lane dashes scroll with CSS. */}
        <div aria-hidden className="absolute bottom-0 left-0 top-0 w-12 overflow-hidden rounded-full bg-[#0e1117]">
          <div className="absolute inset-y-0 left-1/2 w-[3px] -translate-x-1/2 animate-[roaddash_0.9s_linear_infinite] bg-[repeating-linear-gradient(to_bottom,#f3f2ee_0_18px,transparent_18px_40px)] motion-reduce:animate-none" />
          <div data-m-truck className="absolute left-1/2 top-2 w-[26px] -translate-x-1/2">
            <TruckTop src={truckTop} className="h-auto w-full" />
          </div>
        </div>
        <ol className="flex flex-col gap-10 py-6 pl-[72px]">
          {road.milestones.map((m) => (
            <li key={m.title} data-m-reveal>
              <Icon name={m.icon as IconName} className="h-7 w-7 text-ink" />
              <h3 className="mt-3 text-[18px] font-semibold leading-snug">{m.title}</h3>
              <p className="mt-1.5 line-clamp-2 text-[16px] leading-[1.55] text-ink/65">{m.body}</p>
            </li>
          ))}
        </ol>
      </div>
    </section>
  );
}

function Ocean({ shipTop }: { shipTop?: string }) {
  return (
    <section data-m-ocean data-nav-theme="dark" aria-labelledby="m-ocean" className="bg-[#0a2a7a] text-paper">
      <div className="relative aspect-[4/5] w-full overflow-hidden bg-[#0a3a9e]">
        <div data-m-water aria-hidden className="absolute -inset-[10%] animate-[drift_60s_linear_infinite] bg-[length:640px] opacity-80 motion-reduce:animate-none" />
        <div data-m-ship aria-hidden className="absolute left-1/2 top-[50%] w-[26%] -translate-x-1/2">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img data-m-wake alt="" className="absolute left-1/2 top-[-4%] w-[250%] max-w-none -translate-x-1/2" />
          <ShipTop src={shipTop} className="relative h-auto w-full drop-shadow-[0_14px_24px_rgba(0,10,40,0.45)]" />
        </div>
        {/* Headline over the footage, on a dark gradient for contrast. */}
        <div className="absolute inset-x-0 top-0 bg-[linear-gradient(to_bottom,rgba(4,20,70,0.9),rgba(4,20,70,0.7)_65%,transparent)] px-5 pb-16 pt-24">
          <MHeading id="m-ocean" lines={mobileHeadlines.ocean} muted={[2, 3]} />
        </div>
      </div>
      <ul className="grid grid-cols-2 gap-x-4 gap-y-8 px-5 py-16">
        {ocean.features.map((f) => (
          <li key={f.title} data-m-reveal>
            <Icon name={f.icon as IconName} className="h-7 w-7" />
            <h3 className="mt-3 text-[16px] font-semibold leading-snug">{f.title}</h3>
            <p className="mt-1.5 text-[14px] leading-[1.5] text-paper/70">{f.body}</p>
          </li>
        ))}
      </ul>
    </section>
  );
}

function Testimonials() {
  const [active, setActive] = useState(0);
  const [open, setOpen] = useState<number | null>(null);
  return (
    <section data-nav-theme="light" aria-labelledby="m-trust" className="bg-paper py-24 text-ink">
      <div className="px-5">
        <MEyebrow className="text-steel">{trust.eyebrow}</MEyebrow>
        <MHeading id="m-trust" lines={mobileHeadlines.trust} muted={[2]} className="mt-5" />
        <p data-m-reveal className="mt-5 text-[16px] leading-[1.55] text-ink/70">
          {trust.intro}
        </p>
      </div>
      <ul
        data-m-reveal
        onScroll={(e) => setActive(snapIndex(e.currentTarget))}
        className="mt-8 flex snap-x snap-mandatory scroll-px-5 gap-4 overflow-x-auto overscroll-x-contain px-5 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      >
        {testimonials.map((t, i) => (
          <li key={t.name} className="w-[86vw] max-w-[440px] shrink-0 snap-start">
            <figure className="flex h-full flex-col rounded-2xl bg-white p-5 ring-1 ring-ink/5">
              <figcaption className="flex items-center gap-4">
                <Avatar look={t.avatar} className="h-14 w-14 shrink-0 rounded-xl" />
                <span className="min-w-0">
                  <span className="block text-[16px] font-semibold leading-snug">{t.name}</span>
                  <span className="block text-[14px] leading-snug text-ink/60">
                    {t.role}, {t.company}
                  </span>
                </span>
              </figcaption>
              <blockquote className={`mt-5 text-[16px] leading-[1.55] ${open === i ? "" : "line-clamp-6"}`}>“{t.quote}”</blockquote>
              <button
                type="button"
                onClick={() => setOpen(open === i ? null : i)}
                className="mt-2 inline-flex min-h-11 items-center self-start text-[14px] font-medium text-ink/70 underline underline-offset-4"
              >
                {open === i ? "Show less" : "Read more"}
              </button>
            </figure>
          </li>
        ))}
      </ul>
      <div className="px-5">
        <MDots count={testimonials.length} active={active} tone="light" />
        <p className="mt-12 font-mono text-[12px] uppercase tracking-[0.1em] text-steel">Clients include</p>
        <ul aria-label="Selected clients" className="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-[16px] font-medium text-ink/55">
          {clientLogos.map((c) => (
            <li key={c}>{c}</li>
          ))}
        </ul>
      </div>
    </section>
  );
}

function Cta() {
  return (
    <section data-nav-theme="light" aria-labelledby="m-cta" className="border-t border-ink/10 bg-paper px-5 py-24 text-ink">
      <MEyebrow className="text-steel">05 — Next shipment</MEyebrow>
      <MHeading id="m-cta" lines={mobileHeadlines.cta} className="mt-5" />
      <p data-m-reveal className="mt-5 text-[16px] leading-[1.55] text-ink/70">
        {cta.sub}
      </p>
      <MButton href={cta.button.href} variant="brand" className="mt-8">
        {cta.button.label}
      </MButton>
      <a href={`mailto:${company.email}`} className="mt-4 flex min-h-11 items-center text-[16px] text-ink underline underline-offset-4">
        {company.email}
      </a>
      <a href={company.phoneHref} className="flex min-h-11 items-center text-[16px] text-ink/75">
        {company.phone}
      </a>
    </section>
  );
}
