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
    <footer ref={root} data-nav-theme="dark" className="relative overflow-hidden bg-ink px-gutter pb-8 pt-24 text-paper md:pl-[clamp(140px,13vw,220px)]">
      <div className="grid gap-12 border-b border-paper/10 pb-16 md:grid-cols-12">
        <div className="md:col-span-4">
          <Logo />
          <p className="mt-6 max-w-[34ch] text-[14px] leading-relaxed text-paper/60">
            Warehousing, road transport, freight forwarding and customs, run by one accountable team out of Delhi since 2018.
          </p>
          <a href={`mailto:${company.emailGeneral}`} className="mt-6 inline-block text-[15px] underline decoration-paper/30 underline-offset-4 hover:text-brand">
            {company.emailGeneral}
          </a>
          <a href={company.phoneHref} className="mt-2 block text-[14px] text-paper/60 hover:text-brand">
            {company.phone}
          </a>
          <p className="mt-1 text-[12px] text-paper/45">{company.hours}</p>
        </div>
        {cols.map((c) => (
          <nav key={c.title} aria-label={c.title} className="md:col-span-2">
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
        <div className="md:col-span-2">
          <p className="ui-label mb-4 text-steel">Registered office</p>
          <address className="text-[13px] not-italic leading-relaxed text-paper/65">
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

      <div className="mt-10 flex flex-col gap-4 text-[12px] text-paper/50 md:flex-row md:items-center md:justify-between">
        <p>
          © 2026 {company.legalName} · CIN {company.cin} · Incorporated {company.incorporatedLabel}, {company.roc}
        </p>
        <button
          type="button"
          onClick={() => scrollToTarget(lenis, 0)}
          className="ui-label inline-flex items-center gap-2 self-start rounded-full border border-paper/20 px-4 py-2 text-paper/80 hover:border-brand hover:text-brand"
        >
          <Icon name="arrowUp" className="h-3.5 w-3.5" />
          Back to top
        </button>
      </div>

      {/* Studio credit */}
      <div className="-mx-gutter mt-10 border-t border-paper/10 px-gutter pb-1 pt-7 text-center md:-ml-[clamp(140px,13vw,220px)] md:pl-[clamp(140px,13vw,220px)]">
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
