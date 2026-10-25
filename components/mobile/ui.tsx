import Link from "next/link";
import { Arrow } from "@/components/ui/Button";
import { cn } from "@/lib/cn";

/** Phone design system primitives: one gutter (px-5), one type scale, full-width 52px buttons. */

export function MEyebrow({ children, className }: { children: React.ReactNode; className?: string }) {
  return <p className={cn("font-mono text-[12px] uppercase leading-none tracking-[0.1em]", className)}>{children}</p>;
}

/** Display heading with hand-set lines (one block per line, never broken mid-word). */
export function MHeading({
  lines,
  as: Tag = "h2",
  muted = [],
  className,
  id,
}: {
  lines: readonly string[];
  as?: "h1" | "h2";
  muted?: number[];
  className?: string;
  id?: string;
}) {
  return (
    <Tag
      id={id}
      data-m-reveal
      className={cn(
        "display uppercase",
        Tag === "h1" ? "text-[clamp(40px,11vw,56px)] leading-[0.95] tracking-[-0.02em]" : "text-[clamp(32px,9.5vw,44px)] leading-none tracking-[-0.015em]",
        className,
      )}
    >
      {lines.map((l, i) => (
        <span key={l} className={cn("block whitespace-nowrap", muted.includes(i) && "opacity-45")}>
          {l}
        </span>
      ))}
    </Tag>
  );
}

export function MButton({
  href,
  children,
  variant = "light",
  className,
}: {
  href: string;
  children: React.ReactNode;
  variant?: "light" | "dark" | "ghost-dark" | "ghost-light" | "brand";
  className?: string;
}) {
  const styles = {
    light: "bg-paper text-ink",
    dark: "bg-ink text-paper",
    brand: "bg-brand text-ink",
    "ghost-dark": "border border-paper/35 text-paper",
    "ghost-light": "border border-ink/25 text-ink",
  }[variant];
  return (
    <Link href={href} data-m-reveal className={cn("flex h-[52px] w-full items-center justify-between rounded-full px-6 text-[16px] font-medium", styles, className)}>
      <span>{children}</span>
      <Arrow className="h-4 w-4" />
    </Link>
  );
}

/** Dots that follow a native scroll-snap carousel. */
export function MDots({ count, active, tone = "dark" }: { count: number; active: number; tone?: "dark" | "light" }) {
  return (
    <div aria-hidden className="mt-5 flex gap-1.5">
      {Array.from({ length: count }, (_, i) => (
        <span
          key={i}
          className={cn(
            "h-1.5 rounded-full transition-all duration-300",
            i === active ? "w-5 bg-brand" : cn("w-1.5", tone === "dark" ? "bg-paper/30" : "bg-ink/20"),
          )}
        />
      ))}
    </div>
  );
}

/** Index of the snapped card from a horizontal scroll container. */
export function snapIndex(el: HTMLElement) {
  const first = el.firstElementChild as HTMLElement | null;
  if (!first) return 0;
  const step = first.getBoundingClientRect().width + parseFloat(getComputedStyle(el).columnGap || "0");
  return Math.round(el.scrollLeft / step);
}
