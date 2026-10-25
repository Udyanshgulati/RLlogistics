"use client";

import Link from "next/link";
import { useRef } from "react";
import { gsap, useGSAP, MQ } from "@/lib/gsap";
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
        gsap.fromTo(
          "[data-footer-tagline]",
          { autoAlpha: 0, y: 30 },
          { autoAlpha: 1, y: 0, duration: 1, ease: "expo", scrollTrigger: { trigger: "[data-footer-tagline]", start: "top 90%", toggleActions: "play none none reverse" } },
        );
        gsap.fromTo(
          "[data-route]",
          { strokeDashoffset: 1 },
          { strokeDashoffset: 0, duration: 2.2, ease: "power2.out", stagger: 0.12, scrollTrigger: { trigger: "[data-footer-tagline]", start: "top 95%" } },
        );
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
          <a href={`mailto:${company.email}`} className="flex min-h-11 items-center text-[15px] text-paper/80 underline decoration-paper/30 underline-offset-4 lg:hidden">
            {company.email}
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

      <div className="relative mt-10 overflow-hidden">
        <FooterRoutes />
        <p data-footer-tagline className="display relative max-w-[18ch] py-10 text-[clamp(28px,4.4vw,64px)] leading-[1.05]">
          From Delhi to <span className="text-brand">every doorstep.</span>
        </p>
      </div>

      <div className="mt-10 flex flex-col gap-4 text-[14px] leading-relaxed text-paper/55 lg:flex-row lg:items-center lg:justify-between lg:text-[12px] lg:leading-[inherit] lg:text-paper/50">
        <p>
          © 2026 {company.legalName} · CIN {company.cin} · Incorporated {company.incorporatedLabel}, {company.roc}
        </p>
        <button
          type="button"
          onClick={() => scrollToTarget(lenis, 0)}
          className="ui-label inline-flex items-center gap-2 self-start rounded-[10px] border border-paper/20 px-4 py-2 text-paper/80 hover:border-brand hover:text-brand max-lg:min-h-11"
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

/** Faint route lines fanning out from Delhi: the RL motif, drawn in as the footer arrives. */
function FooterRoutes() {
  const routes = [
    "M150 40 C 260 60, 330 120, 420 210",
    "M150 40 C 120 120, 110 180, 90 260",
    "M150 40 C 300 30, 520 40, 760 120",
    "M150 40 C 360 80, 600 160, 980 200",
    "M150 40 C 210 140, 300 200, 520 250",
  ];
  return (
    <svg aria-hidden viewBox="0 0 1000 270" preserveAspectRatio="xMinYMid slice" className="pointer-events-none absolute inset-0 h-full w-full text-paper/15">
      {routes.map((d) => (
        <path key={d} data-route d={d} pathLength={1} fill="none" stroke="currentColor" strokeWidth={1.5} strokeDasharray="0.012 0.008" />
      ))}
      <circle cx={150} cy={40} r={5} className="fill-brand" />
      <circle cx={150} cy={40} r={12} fill="none" className="stroke-brand" strokeOpacity={0.4} />
    </svg>
  );
}
