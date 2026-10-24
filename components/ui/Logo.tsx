import Link from "next/link";
import { company } from "@/content/company";
import { cn } from "@/lib/cn";

export function Logo({ className }: { className?: string }) {
  return (
    <Link href="/" aria-label={`${company.name} ${company.nameSub} — home`} className={cn("group inline-flex flex-col leading-none", className)}>
      <span className="display flex items-center gap-1.5 text-[19px] tracking-tight">
        <span aria-hidden className="inline-block h-2.5 w-2.5 rounded-[2px] bg-brand transition-transform duration-500 ease-expo group-hover:rotate-90" />
        {company.name}
      </span>
      <span className="ui-label mt-1 pl-4 text-[9px] opacity-70">{company.nameSub}</span>
    </Link>
  );
}
