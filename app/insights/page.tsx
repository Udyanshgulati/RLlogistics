import type { Metadata } from "next";
import Image from "next/image";
import Link from "next/link";
import { PageHero } from "@/components/ui/PageHero";
import { Arrow } from "@/components/ui/Button";
import { insights } from "@/content/insights";
import { formatDate } from "@/lib/format";

export const metadata: Metadata = {
  title: "Insights",
  description: "Practical notes from the RL Logistics floor on e-commerce peaks, customs clearance, e-way bills and cold-chain shipping in India.",
};

export default function InsightsPage() {
  return (
    <>
      <PageHero eyebrow="Insights" headline={["Notes from", "the floor."]} intro="What we've learned moving freight across India, written by the people who run the operation." />
      <section data-nav-theme="light" className="bg-paper px-gutter py-[12vh] md:pl-[clamp(140px,13vw,220px)]">
        <ul className="grid gap-x-10 gap-y-16 md:grid-cols-2">
          {insights.map((a) => (
            <li key={a.slug}>
              <Link href={`/insights/${a.slug}`} className="group block">
                <div className="relative aspect-[16/10] overflow-hidden rounded-[2px] bg-ink/5">
                  <Image
                    src={a.cover.src}
                    alt={a.cover.alt}
                    fill
                    sizes="(min-width: 768px) 40vw, 100vw"
                    className="object-cover transition-transform duration-700 ease-expo group-hover:scale-[1.03]"
                  />
                </div>
                <p className="ui-label mt-6 flex gap-3 text-steel">
                  <span className="text-brand">{a.tag}</span>
                  <time dateTime={a.date}>{formatDate(a.date)}</time>· {a.read} read
                </p>
                <h2 className="display mt-3 max-w-[26ch] text-[clamp(1.35rem,2.2vw,2rem)] leading-[1] transition-colors group-hover:text-brand">{a.title}</h2>
                <p className="mt-3 max-w-[56ch] text-[15px] leading-relaxed text-ink/65">{a.excerpt}</p>
                <span className="ui-label mt-5 inline-flex items-center gap-2 border-b border-ink/25 pb-1 group-hover:border-brand group-hover:text-brand">
                  Read the article <Arrow className="transition-transform group-hover:translate-x-1" />
                </span>
              </Link>
            </li>
          ))}
        </ul>
      </section>
    </>
  );
}
