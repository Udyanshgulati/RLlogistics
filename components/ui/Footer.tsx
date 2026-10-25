"use client";

import Link from "next/link";
import { useRef } from "react";
import { gsap, SplitText, useGSAP, MQ } from "@/lib/gsap";
import { useLenis, scrollToTarget } from "@/lib/lenis";
import { company } from "@/content/company";
import { services } from "@/content/services";
import { industries } from "@/content/industries";
import { primaryNav } from "@/content/nav";
import { Logo } from "./Logo";
import { Icon } from "./Icon";

export function Footer() {
  const root = useRef<HTMLElement>(null);
  const lenis = useLenis();

  useGSAP(
    () => {
      const mm = gsap.matchMedia();
      mm.add(MQ.motion, () => {
        const split = SplitText.create("[data-wordmark]", { type: "chars", mask: "chars" });
        gsap.fromTo(
          split.chars,
          { yPercent: 105 },
          {
            yPercent: 0,
            stagger: 0.05,
            ease: "none",
            scrollTrigger: { trigger: "[data-wordmark]", start: "top bottom", end: "bottom bottom-=20", scrub: 1 },
          },
        );
        return () => split.revert();
      });
    },
    { scope: root },
  );

  const cols = [
    { title: "Company", links: primaryNav.filter((l) => ["/about", "/network", "/insights", "/contact"].includes(l.href)) },
    { title: "Services", links: services.map((s) => ({ label: s.title, href: `/services/${s.slug}` })) },
    { title: "Industries", links: industries.map((i) => ({ label: i.title, href: `/industries#${i.slug}` })) },
  ];

  return (
    <footer ref={root} data-nav-theme="dark" className="relative overflow-hidden bg-ink px-gutter pb-[calc(2rem+var(--safe-b))] pt-16 text-paper lg:pt-24 lg:pl-[clamp(140px,13vw,220px)]">
      <div className="grid gap-10 border-b border-paper/10 pb-12 lg:grid-cols-12 lg:gap-12 lg:pb-16">
        <div className="lg:col-span-4">
          <Logo />
          <p className="mt-6 max-w-[34ch] text-[14px] leading-relaxed text-paper/60">
            Warehousing, road transport, freight forwarding and customs, run by one accountable team out of Delhi since 2018.
          </p>
          <a href={`mailto:${company.emailGeneral}`} className="mt-6 inline-block text-[15px] underline decoration-paper/30 underline-offset-4 hover:text-brand max-lg:mt-4 max-lg:inline-flex max-lg:min-h-11 max-lg:items-center">
            {company.emailGeneral}
          </a>
          <a href={company.phoneHref} className="mt-2 block text-[14px] text-paper/60 hover:text-brand max-lg:mt-0 max-lg:flex max-lg:min-h-11 max-lg:items-center max-lg:text-[15px] max-lg:text-paper/80">
            {company.phone}
          </a>
          <p className="mt-1 text-[14px] text-paper/55 lg:text-[12px] lg:text-paper/45">{company.hours}</p>
        </div>
        {/* Phones/tablets: link groups collapse into accordions. */}
        <div className="divide-y divide-paper/10 border-y border-paper/10 sm:max-lg:grid sm:max-lg:grid-cols-2 sm:max-lg:gap-x-6 lg:hidden">
          {cols.map((c) => (
            <details key={c.title} className="group">
              <summary className="ui-label flex min-h-[52px] cursor-pointer list-none items-center justify-between text-steel [&::-webkit-details-marker]:hidden">
                {c.title}
                <span aria-hidden className="text-[18px] leading-none text-paper/60 transition-transform group-open:rotate-45">+</span>
              </summary>
              <ul className="pb-3 text-[15px]">
                {c.links.map((l) => (
                  <li key={l.href + l.label}>
                    <Link href={l.href} className="flex min-h-11 items-center text-paper/80">
                      {l.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </details>
          ))}
        </div>
        {cols.map((c) => (
          <nav key={c.title} aria-label={c.title} className="max-lg:hidden lg:col-span-2">
            <p className="ui-label mb-4 text-steel">{c.title}</p>
            <ul className="flex flex-col gap-2 text-[14px]">
              {c.links.map((l) => (
                <li key={l.href + l.label}>
                  <Link href={l.href} className="text-paper/75 transition-colors hover:text-brand">
                    {l.label}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>
        ))}
        <div className="lg:col-span-2">
          <p className="ui-label mb-4 text-steel">Registered office</p>
          <address className="text-[14px] not-italic leading-relaxed text-paper/65 lg:text-[13px]">
            {company.address.lines.map((l) => (
              <span key={l} className="block">
                {l}
              </span>
            ))}
          </address>
        </div>
      </div>

      <p data-wordmark aria-hidden className="display select-none whitespace-nowrap pt-10 text-[clamp(2.4rem,8.6vw,12rem)] leading-[0.85] tracking-[-0.03em] text-paper">
        RL Logistics
      </p>

      <div className="mt-10 flex flex-col gap-4 text-[14px] leading-relaxed text-paper/55 lg:flex-row lg:items-center lg:justify-between lg:text-[12px] lg:leading-[inherit] lg:text-paper/50">
        <p>
          © 2026 {company.legalName} · CIN {company.cin} · Incorporated {company.incorporatedLabel}, {company.roc}
        </p>
        <button
          type="button"
          onClick={() => scrollToTarget(lenis, 0)}
          className="ui-label inline-flex items-center gap-2 self-start rounded-full border border-paper/20 px-4 py-2 text-paper/80 hover:border-brand hover:text-brand max-lg:min-h-11"
        >
          <Icon name="arrowUp" className="h-3.5 w-3.5" />
          Back to top
        </button>
      </div>

      {/* Studio credit */}
      <div className="-mx-gutter mt-10 border-t border-paper/10 px-gutter pb-1 pt-7 text-center lg:-ml-[clamp(140px,13vw,220px)] lg:pl-[clamp(140px,13vw,220px)]">
        <a
          href="https://thodalogic.com"
          target="_blank"
          rel="noreferrer"
          className="group inline-block font-sans text-[10px] font-medium uppercase tracking-[0.32em] text-paper/45 transition-colors hover:text-paper/70 sm:text-[11px]"
        >
          Designed and developed by{" "}
          <span className="font-bold text-paper transition-colors group-hover:text-brand">Thoda Logic</span>
        </a>
      </div>
    </footer>
  );
}
