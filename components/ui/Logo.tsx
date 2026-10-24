import Image from "next/image";
import Link from "next/link";
import { company } from "@/content/company";
import { cn } from "@/lib/cn";

/** Brand logo on its white base plate — reads on both the dark and paper sections. */
export function Logo({ className, size = "md" }: { className?: string; size?: "md" | "lg" }) {
  const h = size === "lg" ? 44 : 26;
  return (
    <Link
      href="/"
      aria-label={`${company.legalName} — home`}
      className={cn(
        "group inline-flex items-center rounded-[6px] bg-white shadow-[0_1px_0_rgba(10,13,20,0.06),0_8px_24px_-12px_rgba(10,13,20,0.35)] ring-1 ring-black/5 transition-transform duration-500 ease-expo hover:-translate-y-0.5",
        size === "lg" ? "px-5 py-4" : "px-3 py-2.5",
        className,
      )}
    >
      <Image
        src="/brand/rl-logo.png"
        alt={`${company.name} and Warehousing`}
        width={Math.round(h * 4.11)}
        height={h}
        priority
        className="block h-auto"
        style={{ height: h, width: "auto" }}
      />
    </Link>
  );
}
