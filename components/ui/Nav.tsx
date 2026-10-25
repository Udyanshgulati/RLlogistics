"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { gsap, useGSAP, EXPO } from "@/lib/gsap";
import { useLenis } from "@/lib/lenis";
import { useLoader } from "@/lib/loader";
import { primaryNav, ctaNav } from "@/content/nav";
import { company } from "@/content/company";
import { Logo } from "./Logo";
import { Arrow } from "./Button";
import { cn } from "@/lib/cn";

/**
 * Global chrome: logo, left vertical nav (desktop), utility links + CTA pill, and a full-screen menu.
 * Colours follow the section under the header: any element with data-nav-theme="dark|light".
 * After the first scroll the vertical list folds away into a "Menu" toggle.
 */
export function Nav() {
  const root = useRef<HTMLElement>(null);
  const routeFill = useRef<HTMLSpanElement>(null);
  const pathname = usePathname();
  const lenis = useLenis();
  const { done } = useLoader();
  const [open, setOpen] = useState(false);
  const [folded, setFolded] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const [theme, setTheme] = useState<"dark" | "light">("dark");

  useEffect(() => setOpen(false), [pathname]);

  useEffect(() => {
    let raf = 0;
    const probe = () => {
      raf = 0;
      setFolded(window.scrollY > 80);
      setScrolled(window.scrollY > 8);
      const max = document.documentElement.scrollHeight - window.innerHeight;
      if (routeFill.current) routeFill.current.style.transform = `scaleX(${max > 0 ? Math.min(1, window.scrollY / max) : 0})`;
      const hit = document
        .elementsFromPoint(window.innerWidth / 2, 36)
        .find((el) => !root.current?.contains(el) && el.closest("[data-nav-theme]"));
      const t = hit?.closest("[data-nav-theme]")?.getAttribute("data-nav-theme");
      setTheme(t === "dark" ? "dark" : "light");
    };
    // Scrubbed scenes keep animating ~1s after the last scroll event, so probe again once they settle.
    let settle = 0;
    const onScroll = () => {
      raf ||= requestAnimationFrame(probe);
      clearTimeout(settle);
      settle = window.setTimeout(() => (raf ||= requestAnimationFrame(probe)), 1200);
    };
    probe();
    window.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("resize", onScroll);
    return () => {
      cancelAnimationFrame(raf);
      clearTimeout(settle);
      window.removeEventListener("scroll", onScroll);
      window.removeEventListener("resize", onScroll);
    };
  }, [pathname]);

  useEffect(() => {
    if (open) lenis?.stop();
    else lenis?.start();
    document.documentElement.style.overflow = open ? "hidden" : "";
  }, [open, lenis]);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && setOpen(false);
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  // Intro stagger once the loader has gone.
  useGSAP(
    () => {
      if (!done) {
        gsap.set("[data-nav-in]", { autoAlpha: 0 });
        return;
      }
      if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
        gsap.set("[data-nav-in]", { autoAlpha: 1 });
        return;
      }
      gsap.fromTo("[data-nav-in]", { autoAlpha: 0, y: 14 }, { autoAlpha: 1, y: 0, duration: 0.9, ease: EXPO, stagger: 0.045, delay: 0.15 });
    },
    { scope: root, dependencies: [done] },
  );

  // Vertical list folds into the Menu toggle.
  useGSAP(
    () => {
      if (!done) return;
      gsap.to("[data-side-item]", {
        autoAlpha: folded ? 0 : 1,
        x: folded ? -12 : 0,
        duration: 0.5,
        ease: EXPO,
        stagger: folded ? 0.02 : -0.02,
        overwrite: true,
      });
      gsap.to("[data-side-toggle]", { autoAlpha: folded ? 1 : 0, duration: 0.4, overwrite: true, delay: folded ? 0.15 : 0 });
    },
    { scope: root, dependencies: [folded, done] },
  );

  // Menu overlay open/close.
  useGSAP(
    () => {
      const overlay = "[data-menu]";
      if (open) {
        gsap
          .timeline()
          .set(overlay, { visibility: "visible" })
          .fromTo(overlay, { clipPath: "inset(0 0 100% 0)" }, { clipPath: "inset(0 0 0% 0)", duration: 0.8, ease: EXPO })
          .fromTo("[data-menu-link]", { yPercent: 110 }, { yPercent: 0, duration: 0.9, ease: EXPO, stagger: 0.04 }, 0.2)
          .fromTo("[data-menu-meta]", { autoAlpha: 0 }, { autoAlpha: 1, duration: 0.6 }, 0.5);
      } else {
        gsap
          .timeline()
          .to(overlay, { clipPath: "inset(0 0 100% 0)", duration: 0.6, ease: "power3.inOut" })
          .set(overlay, { visibility: "hidden" });
      }
    },
    { scope: root, dependencies: [open] },
  );

  const isActive = (href: string) => href !== "/" && pathname.startsWith(href.split("#")[0]);
  const fg = theme === "dark" || open ? "text-paper" : "text-ink";

  return (
    <header ref={root}>
      {/* Phones/tablets: 64px bar, logo left, menu right; turns solid with a blur once the page scrolls. */}
      <div
        className={cn(
          "fixed inset-x-0 top-0 z-50 flex h-[calc(64px+var(--safe-t))] items-end transition-colors duration-300 lg:hidden",
          fg,
          // Solid tint, no backdrop-filter: blurring whatever scrolls underneath costs a full repaint per frame on phones.
          scrolled && !open && (theme === "dark" ? "bg-ink/95" : "bg-paper/95 shadow-[0_1px_0_rgba(11,31,77,0.06)]"),
        )}
      >
        {/* Route progress: an amber lane marking that fills along the bottom edge as you travel the page. */}
        <span aria-hidden ref={routeFill} className="absolute inset-x-0 bottom-0 h-[2px] origin-left scale-x-0 bg-[repeating-linear-gradient(90deg,rgb(var(--brand))_0_14px,transparent_14px_22px)]" />
        <div className="flex h-16 w-full items-center justify-between pl-[calc(20px+var(--safe-l))] pr-[calc(12px+var(--safe-r))]">
          <Logo className="origin-left scale-[0.9]" />
          <button
            type="button"
            onClick={() => setOpen((v) => !v)}
            aria-expanded={open}
            aria-controls="site-menu"
            aria-label={open ? "Close menu" : "Open menu"}
            className="relative z-[70] flex h-11 w-11 items-center justify-center rounded-[10px]"
          >
            <span className="relative block h-3 w-6">
              <span className={cn("absolute left-0 top-0 h-[1.5px] w-full bg-current transition-transform duration-300", open && "translate-y-[5px] rotate-45")} />
              <span className={cn("absolute bottom-0 left-0 h-[1.5px] w-full bg-current transition-transform duration-300", open && "-translate-y-[5px] -rotate-45")} />
            </span>
          </button>
        </div>
      </div>

      {/* Logo + vertical nav */}
      <div className={cn("pointer-events-none fixed inset-x-0 top-0 z-50 transition-colors duration-500 max-lg:hidden", fg)}>
        <div className="flex items-start p-gutter pt-[calc(var(--gutter)+var(--safe-t))] pl-[calc(var(--gutter)+var(--safe-l))] pr-[calc(var(--gutter)+var(--safe-r))]">
          <div data-nav-in className="pointer-events-auto">
            <Logo />
          </div>
        </div>
        <nav aria-hidden className="hidden">
          <ul className="pointer-events-auto flex flex-col gap-[7px]">
            {primaryNav.map((item, i) => (
              <li key={item.href} data-nav-in>
                <div data-side-item>
                  <Link
                    href={item.href}
                    className={cn(
                      "ui-label group relative inline-flex items-center gap-2 py-0.5",
                      isActive(item.href) ? "opacity-100" : "opacity-80 hover:opacity-100",
                    )}
                  >
                    <span className="w-5 text-[9px] opacity-50">{String(i + 1).padStart(2, "0")}</span>
                    <span className="relative overflow-hidden">
                      <span className="block transition-transform duration-500 ease-expo group-hover:-translate-y-full">{item.label}</span>
                      <span aria-hidden className="absolute inset-0 translate-y-full transition-transform duration-500 ease-expo group-hover:translate-y-0">
                        {item.label}
                      </span>
                    </span>
                    <span aria-hidden className="h-px w-0 bg-current transition-all duration-500 ease-expo group-hover:w-4" />
                  </Link>
                </div>
              </li>
            ))}
          </ul>
        </nav>
      </div>

      {/* Utility links, CTA, mobile menu toggle */}
      <div className={cn("pointer-events-none fixed inset-x-0 top-0 z-50 flex items-start justify-end gap-3 p-gutter max-lg:hidden pt-[calc(var(--gutter)+var(--safe-t))] pl-[calc(var(--gutter)+var(--safe-l))] pr-[calc(var(--gutter)+var(--safe-r))] transition-colors duration-500", fg)}>
        {/* Desktop: primary links sit in the top bar (no vertical side list). */}
        <nav aria-label="Primary" className="pointer-events-auto hidden h-10 items-center gap-1 lg:flex" data-nav-in>
          {primaryNav.filter((item) => item.href !== "/contact").map((item) => (
            <Link
              key={item.href}
              href={item.href}
              className={cn(
                "relative rounded-[10px] px-3 py-2 text-[14px] font-medium transition-opacity after:absolute after:inset-x-3 after:bottom-1 after:h-[2px] after:origin-left after:scale-x-0 after:bg-brand after:transition-transform after:duration-300 hover:after:scale-x-100",
                isActive(item.href) ? "opacity-100 after:scale-x-100" : "opacity-80 hover:opacity-100",
              )}
            >
              {item.label}
            </Link>
          ))}
        </nav>
        <Link
          href="/contact#quote"
          data-nav-in
          className="group pointer-events-auto hidden h-10 items-center gap-2 rounded-[10px] bg-brand px-4 max-lg:h-11 text-[13px] font-medium text-ink transition-colors hover:bg-paper sm:inline-flex"
        >
          Get a quote
          <Arrow className="transition-transform duration-300 group-hover:translate-x-0.5" />
        </Link>
        <button
          type="button"
          data-nav-in
          onClick={() => setOpen((v) => !v)}
          aria-expanded={open}
          aria-controls="site-menu"
          className="ui-label pointer-events-auto inline-flex h-11 min-w-11 items-center gap-2 rounded-[10px] border px-4 lg:hidden"
          style={{ borderColor: "color-mix(in srgb, currentColor 30%, transparent)" }}
        >
          <span className="relative block h-2 w-4">
            <span className={cn("absolute left-0 top-0 h-px w-full bg-current transition-transform duration-300", open && "translate-y-1 rotate-45")} />
            <span className={cn("absolute bottom-0 left-0 h-px w-full bg-current transition-transform duration-300", open && "-translate-y-[3px] -rotate-45")} />
          </span>
          {open ? "Close" : "Menu"}
        </button>
      </div>

      {/* Desktop: Menu toggle that replaces the folded vertical list */}
      <button
        type="button"
        data-side-toggle
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        aria-controls="site-menu"
        className={cn("ui-label invisible fixed left-gutter top-[112px] z-50 hidden items-center gap-2 opacity-0 transition-colors duration-500", fg)}
      >
        <span className="flex flex-col gap-[3px]">
          <span className="block h-px w-4 bg-current" />
          <span className="block h-px w-2.5 bg-current" />
        </span>
        Menu
      </button>

      {/* Full-screen menu */}
      <div
        id="site-menu"
        data-menu
        className="invisible fixed inset-x-0 top-0 z-[60] flex h-[100vh] h-[100dvh] flex-col overflow-y-auto overscroll-contain bg-ink text-paper"
        style={{ clipPath: "inset(0 0 100% 0)" }}
        aria-hidden={!open}
      >
        <div className="flex items-start justify-between p-gutter pt-[calc(var(--gutter)+var(--safe-t))] pl-[calc(var(--gutter)+var(--safe-l))] pr-[calc(var(--gutter)+var(--safe-r))]">
          <Logo />
          <button type="button" onClick={() => setOpen(false)} className="ui-label inline-flex h-10 items-center rounded-[10px] border border-paper/30 px-4 max-lg:h-11">
            Close
          </button>
        </div>
        <nav aria-label="Menu" className="flex flex-1 flex-col justify-center px-gutter">
          <ul>
            {[{ label: "Home", href: "/" }, ...primaryNav].map((item, i) => (
              <li key={item.href} className="overflow-hidden border-t border-paper/10 last:border-b">
                <Link
                  href={item.href}
                  tabIndex={open ? 0 : -1}
                  data-menu-link
                  className="display group flex items-baseline gap-4 py-2 text-[clamp(2rem,9vw,4.75rem)] leading-[0.95] max-lg:min-h-[52px] max-lg:items-center transition-colors hover:text-brand"
                >
                  <span className="ui-label w-8 text-steel">{String(i).padStart(2, "0")}</span>
                  {item.label}
                </Link>
              </li>
            ))}
          </ul>
        </nav>
        <div data-menu-meta className="grid gap-4 p-gutter pb-[calc(var(--gutter)+var(--safe-b))] text-[13px] text-steel sm:grid-cols-3 max-lg:text-[14px]">
          {/* Phones/tablets: quote CTA + tap-to-call up front */}
          <div className="flex flex-col gap-3 lg:hidden sm:col-span-3">
            <Link href={ctaNav.href} tabIndex={open ? 0 : -1} className="inline-flex h-12 items-center justify-center gap-2 rounded-[10px] bg-brand px-5 text-[15px] font-medium text-ink">
              Get a quote <Arrow />
            </Link>
            <a href={company.phoneHref} tabIndex={open ? 0 : -1} className="inline-flex min-h-11 items-center text-paper">
              {company.phone}
            </a>
          </div>
          <a href={`mailto:${company.email}`} tabIndex={open ? 0 : -1} className="inline-flex min-h-11 items-center hover:text-paper lg:min-h-0">
            {company.email}
          </a>
          <span>{company.address.lines.join(", ")}</span>
          <span className="sm:text-right">CIN {company.cin}</span>
        </div>
      </div>
    </header>
  );
}
