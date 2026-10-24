import Link from "next/link";
import { cn } from "@/lib/cn";

type Props = {
  href: string;
  children: React.ReactNode;
  variant?: "filled" | "ghost" | "brand";
  tone?: "dark" | "light";
  className?: string;
  arrow?: boolean;
};

export function Arrow({ className }: { className?: string }) {
  return (
    <svg aria-hidden viewBox="0 0 16 16" className={cn("h-3.5 w-3.5", className)} fill="none" stroke="currentColor" strokeWidth="1.5">
      <path d="M2 8h11M9 4l4 4-4 4" />
    </svg>
  );
}

/** Pill button. `tone` is the background it sits on. */
export function Button({ href, children, variant = "filled", tone = "dark", className, arrow = true }: Props) {
  const styles = {
    filled: tone === "dark" ? "bg-paper text-ink hover:bg-brand" : "bg-ink text-paper hover:bg-brand hover:text-ink",
    ghost:
      tone === "dark"
        ? "border border-paper/30 text-paper hover:border-brand hover:text-brand"
        : "border border-ink/25 text-ink hover:border-brand hover:text-brand",
    brand: "bg-brand text-ink hover:bg-paper",
  }[variant];

  return (
    <Link
      href={href}
      className={cn(
        "group inline-flex h-11 items-center gap-3 rounded-full px-5 text-[13px] font-medium tracking-wide transition-colors duration-300",
        styles,
        className,
      )}
    >
      <span>{children}</span>
      {arrow && <Arrow className="transition-transform duration-300 ease-expo group-hover:translate-x-1" />}
    </Link>
  );
}
