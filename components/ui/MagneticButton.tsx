import Link from "next/link";
import { Arrow } from "./Button";

/**
 * Closing CTA: a wide block with chamfered corners, cut like a shipping container's corner castings.
 * (File name kept for the existing imports; there is no magnetic/cursor effect any more.)
 */
export function MagneticButton({ href, children }: { href: string; children: React.ReactNode; radius?: number }) {
  return (
    <Link
      href={href}
      className="group relative inline-flex h-[84px] min-w-[300px] items-center justify-between gap-8 bg-brand px-8 text-[17px] font-semibold text-ink transition-colors duration-300 hover:bg-ink hover:text-paper"
      style={{ clipPath: "polygon(0 0, calc(100% - 18px) 0, 100% 18px, 100% 100%, 18px 100%, 0 calc(100% - 18px))" }}
    >
      <span>{children}</span>
      <Arrow className="h-5 w-5 transition-transform duration-300 group-hover:translate-x-1" />
    </Link>
  );
}
