"use client";

import { useRef } from "react";
import { gsap, SplitText, useGSAP, MQ } from "@/lib/gsap";
import { trust } from "@/content/home";
import { testimonials, clientLogos } from "@/content/testimonials";
import { RevealHeading, buildRevealTl } from "@/components/text/RevealHeading";
import { PlaneTop } from "@/components/illustrations/PlaneTop";
import { cloudTexture, whenNear } from "@/lib/textures";
import { tokenColor } from "@/lib/tokens";
import { cn } from "@/lib/cn";
import type { AssetOverrides } from "@/lib/asset-slots";

/**
 * SCENE 7 — through the clouds, a cargo plane crosses the frame and clears the sky to paper. Then a pinned
 * testimonial list: the active quote reads steel → ink word by word while the others rest at 20%.
 */
export function Scene7Trust({ assets = {} }: { assets?: AssetOverrides }) {
  const root = useRef<HTMLElement>(null);

  useGSAP(
    () => {
      const el = root.current!;
      const clouds = [assets.cloud1, assets.cloud2, assets.cloud3];
      const stopNear = whenNear(el, () =>
        el.querySelectorAll<HTMLImageElement>("[data-cloud]").forEach((c, i) => (c.src = clouds[i] ?? cloudTexture(11 + i * 7))),
      );

      const mm = gsap.matchMedia();
      mm.add({ motion: MQ.motion, mobile: MQ.mobile }, (ctx) => {
        if (!ctx.conditions?.motion) return;
        const heading = buildRevealTl(el.querySelector<HTMLElement>("[data-trust-heading] [data-reveal]")!);
        heading.tl.pause(0);
        const items = gsap.utils.toArray<HTMLElement>("[data-testimonial]");
        const quotes = items.map((it) => SplitText.create(it.querySelector("[data-quote]")!, { type: "words", tag: "span", aria: "none" }));
        const list = el.querySelector<HTMLElement>("[data-list]")!;

        const tl = gsap.timeline({
          defaults: { ease: "none" },
          scrollTrigger: {
            trigger: el,
            start: "top top",
            end: ctx.conditions.mobile ? "+=520%" : "+=640%",
            pin: true,
            scrub: 1,
            anticipatePin: 1,
            invalidateOnRefresh: true,
          },
        });

        // Plane crosses; clouds part; sky clears to paper.
        tl.fromTo("[data-plane]", { autoAlpha: 1, xPercent: -130, yPercent: 10, rotate: -7 }, { autoAlpha: 1, xPercent: 64, yPercent: -36, scale: 0.55, rotate: -14, duration: 3, ease: "power1.inOut" }, 0)
          .to("[data-cloud='0']", { xPercent: -70, yPercent: -20, autoAlpha: 0, duration: 2.4, ease: "power1.in" }, 0.6)
          .to("[data-cloud='1']", { xPercent: 70, autoAlpha: 0, duration: 2.4, ease: "power1.in" }, 0.7)
          .to("[data-cloud='2']", { yPercent: 80, autoAlpha: 0, duration: 2.4, ease: "power1.in" }, 0.8)
          .to("[data-sky]", { autoAlpha: 0, duration: 1.8 }, 1)
          .add(heading.tl.tweenFromTo(0, heading.tl.duration(), { duration: 1 }), 2.2)
          .fromTo("[data-trust-intro], [data-list-wrap]", { autoAlpha: 0, y: 30 }, { autoAlpha: 1, y: 0, duration: 0.8, stagger: 0.2 }, 2.8);

        // Testimonials: slide the list so the active one sits in view; scrub its words.
        items.forEach((item, i) => {
          const at = 3.8 + i * 2.2;
          tl.to(list, { y: () => -item.offsetTop, duration: 0.8, ease: "power2.inOut" }, at)
            .to(items, { opacity: (j: number) => (j === i ? 1 : 0.2), duration: 0.6 }, at)
            .fromTo(quotes[i].words, { color: tokenColor("steel", 0.35) }, { color: tokenColor("ink"), stagger: 0.03, duration: 0.2 }, at + 0.4);
        });

        return () => {
          heading.split.revert();
          quotes.forEach((q) => q.revert());
        };
      });

      mm.add(MQ.reduced, () => el.classList.add("is-static"));
      return stopNear;
    },
    { scope: root },
  );

  return (
    <section
      ref={root}
      data-scene="trust"
      data-nav-theme="light"
      className="group/trust relative h-[100svh] overflow-hidden bg-paper text-ink [&.is-static]:h-auto"
      aria-labelledby="trust-title"
    >
      {/* Plane — keeps its tail parked at the right edge afterwards */}
      <div data-plane className="pointer-events-none invisible absolute left-0 top-[6%] z-30 w-[115vw] max-w-none group-[.is-static]/trust:hidden md:top-[-4%]">
        <PlaneTop src={assets.planeTop} className="h-auto w-full" />
      </div>

      <div className="relative z-10 grid h-full gap-10 px-gutter pb-[8vh] pt-[16vh] md:grid-cols-12 md:pl-[clamp(140px,13vw,220px)]">
        <div className="md:col-span-6">
          <p className="eyebrow mb-4 text-steel">{trust.eyebrow}</p>
          <div data-trust-heading id="trust-title">
            <RevealHeading lines={trust.headline} mutedLines={[2]} mode="external" className="whitespace-nowrap text-[clamp(1.9rem,3.4vw,3.6rem)] leading-[0.92]" />
          </div>
          <p data-trust-intro className="mt-6 max-w-[38ch] text-[14px] leading-relaxed text-ink/65">
            {trust.intro}
          </p>
        </div>

        <div data-list-wrap className="relative overflow-hidden md:col-span-6 md:mt-[12vh] max-md:h-[46vh] group-[.is-static]/trust:h-auto group-[.is-static]/trust:overflow-visible">
          <ul data-list className="flex flex-col gap-14 will-change-transform">
            {testimonials.map((t) => (
              <li key={t.name} data-testimonial className="grid gap-5 sm:grid-cols-[150px_1fr]">
                <div className="flex items-center gap-3 sm:flex-col sm:items-start">
                  <span
                    aria-hidden
                    className="flex h-14 w-14 items-center justify-center rounded-[2px] bg-[linear-gradient(135deg,#d9dadd,#b9bcc3)] font-mono text-[13px] tracking-widest text-ink/70 sm:h-[110px] sm:w-[110px] sm:text-[18px]"
                  >
                    {t.initials}
                  </span>
                  <div>
                    <p className="display text-[13px] leading-tight">{t.name}</p>
                    <p className="text-[12px] text-ink/55">
                      {t.role}
                      <br />
                      {t.company}
                    </p>
                  </div>
                </div>
                <blockquote data-quote className="text-[clamp(1.05rem,1.6vw,1.45rem)] leading-snug">
                  “{t.quote}”
                </blockquote>
              </li>
            ))}
          </ul>
        </div>
      </div>

      {/* Clouds + sky carried over from the ocean */}
      <div data-sky aria-hidden className="pointer-events-none absolute inset-0 z-20 bg-[#e9eff8] group-[.is-static]/trust:hidden" />
      {[0, 1, 2].map((i) => (
        // eslint-disable-next-line @next/next/no-img-element
        <img
          key={i}
          data-cloud={i}
          alt=""
          aria-hidden
          className={cn(
            "pointer-events-none absolute z-20 w-[120vw] max-w-none group-[.is-static]/trust:hidden",
            i === 0 && "-left-[10vw] top-[-10vh]",
            i === 1 && "-right-[10vw] top-[20vh]",
            i === 2 && "-left-[10vw] bottom-[-20vh]",
          )}
        />
      ))}
      <ClientStrip />
    </section>
  );
}

function ClientStrip() {
  const row = [...clientLogos, ...clientLogos];
  return (
    <div aria-label="Selected clients" className="absolute inset-x-0 bottom-0 z-10 overflow-hidden border-t border-ink/10 py-4 group-[.is-static]/trust:relative">
      <ul className="flex w-max animate-[marquee_40s_linear_infinite] gap-16 motion-reduce:animate-none">
        {row.map((name, i) => (
          <li key={i} aria-hidden={i >= clientLogos.length} className="display whitespace-nowrap text-[18px] text-ink/25">
            {name}
          </li>
        ))}
      </ul>
    </div>
  );
}
