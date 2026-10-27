import type { Metadata } from "next";
import Image from "next/image";
import Link from "next/link";
import { notFound } from "next/navigation";
import { PageHero } from "@/components/ui/PageHero";
import { Icon } from "@/components/ui/Icon";
import { Button, Arrow } from "@/components/ui/Button";
import { RevealHeading } from "@/components/text/RevealHeading";
import { services } from "@/content/services";
import { company } from "@/content/company";

type Params = { slug: string };

export function generateStaticParams() {
  return services.map((s) => ({ slug: s.slug }));
}

export async function generateMetadata({ params }: { params: Promise<Params> }): Promise<Metadata> {
  const { slug } = await params;
  const s = services.find((x) => x.slug === slug);
  return s ? { title: `${s.title} in Delhi & across India`, description: s.short, alternates: { canonical: `/services/${s.slug}` } } : {};
}

export default async function ServicePage({ params }: { params: Promise<Params> }) {
  const { slug } = await params;
  const index = services.findIndex((x) => x.slug === slug);
  if (index < 0) notFound();
  const s = services[index];
  const next = services[(index + 1) % services.length];

  const serviceLd = {
    "@context": "https://schema.org",
    "@type": "Service",
    name: s.title,
    description: s.short,
    serviceType: s.title,
    url: `${company.url}/services/${s.slug}`,
    areaServed: { "@type": "Country", name: "India" },
    provider: { "@id": `${company.url}/#organization` },
  };

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(serviceLd) }} />
      <PageHero eyebrow={`Service ${s.index} · ${s.title}`} headline={s.hero.split(/(?<=\.)\s+|(?<=,)\s+/)} intro={s.intro}>
        <div className="relative mt-10 flex flex-wrap gap-3">
          <Button href="/contact#quote" variant="brand">
            Get a quote
          </Button>
          <Button href="/services" variant="ghost" tone="dark">
            All services
          </Button>
        </div>
      </PageHero>

      <div data-nav-theme="light" className="bg-paper px-gutter pt-[10vh] md:pl-[clamp(140px,13vw,220px)]">
        <div className="relative aspect-[21/9] w-full max-w-[1100px] overflow-hidden rounded-[2px] bg-ink/5">
          <Image src={s.photo.src} alt={s.photo.alt} fill sizes="(min-width: 1200px) 1100px, 100vw" className="object-cover" />
        </div>
      </div>

      <section data-nav-theme="light" className="bg-paper px-gutter py-[12vh] md:pl-[clamp(140px,13vw,220px)]">
        <RevealHeading lines={["What's included"]} className="mb-12 text-[clamp(2rem,4.4vw,4.4rem)]" />
        <ul className="grid gap-x-10 gap-y-12 sm:grid-cols-2">
          {s.capabilities.map((c, i) => (
            <li key={c.title} className="border-t border-ink/15 pt-5">
              <span className="ui-label text-steel">{String(i + 1).padStart(2, "0")}</span>
              <h3 className="display mt-3 text-[clamp(1.1rem,1.6vw,1.5rem)] leading-tight">{c.title}</h3>
              <p className="mt-3 max-w-[48ch] text-[15px] leading-relaxed text-ink/65">{c.body}</p>
            </li>
          ))}
        </ul>
      </section>

      <section data-nav-theme="dark" className="bg-ink px-gutter py-[12vh] text-paper md:pl-[clamp(140px,13vw,220px)]">
        <dl className="grid gap-10 sm:grid-cols-3">
          {s.facts.map((f) => (
            <div key={f.label} className="border-t border-paper/15 pt-5">
              <dd className="display text-[clamp(2.4rem,5vw,4.6rem)] leading-none">{f.value}</dd>
              <dt className="mt-3 text-[14px] text-paper/60">{f.label}</dt>
            </div>
          ))}
        </dl>
        <div className="mt-14">
          <p className="ui-label mb-4 text-steel">Key lanes & locations</p>
          <ul className="flex flex-wrap gap-2">
            {s.lanes.map((l) => (
              <li key={l} className="ui-label rounded-[10px] border border-paper/20 px-4 py-2 text-paper/80">
                {l}
              </li>
            ))}
          </ul>
        </div>
      </section>

      <section data-nav-theme="light" className="bg-paper px-gutter py-[12vh] md:pl-[clamp(140px,13vw,220px)]">
        <Link href={`/services/${next.slug}`} className="group flex flex-col gap-4 border-y border-ink/15 py-10 md:flex-row md:items-center md:justify-between">
          <span className="ui-label text-steel">Next service</span>
          <span className="display flex items-center gap-4 text-[clamp(1.8rem,4vw,4rem)] leading-none transition-colors group-hover:text-brand">
            <Icon name={next.icon} className="h-10 w-10" />
            {next.title}
            <Arrow className="h-8 w-8 transition-transform duration-500 ease-expo group-hover:translate-x-2" />
          </span>
        </Link>
      </section>
    </>
  );
}
