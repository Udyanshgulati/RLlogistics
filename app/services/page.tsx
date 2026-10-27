import type { Metadata } from "next";
import Link from "next/link";
import { PageHero } from "@/components/ui/PageHero";
import { Icon } from "@/components/ui/Icon";
import { Arrow } from "@/components/ui/Button";
import { services } from "@/content/services";

export const metadata: Metadata = {
  alternates: { canonical: "/services" },
  title: "Services",
  description: "Warehousing & 3PL, FTL/PTL road transport, air and ocean freight forwarding, customs clearance, last-mile delivery and project cargo across India.",
};

export default function ServicesPage() {
  return (
    <>
      <PageHero
        eyebrow="Services"
        headline={["Under", "one roof."]}
        intro="Six services, one operator. Use one of them, or let us run the whole chain from the factory gate to the customer's door."
      />
      <section data-nav-theme="light" className="bg-paper px-gutter py-[12vh] md:pl-[clamp(140px,13vw,220px)]">
        <ul className="border-t border-ink/15">
          {services.map((s) => (
            <li key={s.slug} className="border-b border-ink/15">
              <Link href={`/services/${s.slug}`} className="group grid items-center gap-4 py-8 md:grid-cols-[80px_60px_1fr_1.2fr_40px] md:py-10">
                <span className="ui-label text-steel">{s.index}</span>
                <Icon name={s.icon} className="h-8 w-8 transition-transform duration-500 ease-expo group-hover:translate-x-1" />
                <span className="display text-[clamp(1.6rem,3.2vw,3rem)] leading-none transition-colors group-hover:text-brand">{s.title}</span>
                <span className="max-w-[46ch] text-[14px] leading-relaxed text-ink/65">{s.short}</span>
                <Arrow className="hidden h-5 w-5 transition-transform duration-500 ease-expo group-hover:translate-x-2 md:block" />
              </Link>
            </li>
          ))}
        </ul>
      </section>
    </>
  );
}
