import type { Metadata } from "next";
import Image from "next/image";
import Link from "next/link";
import { notFound } from "next/navigation";
import { RevealHeading } from "@/components/text/RevealHeading";
import { Arrow, Button } from "@/components/ui/Button";
import { insights } from "@/content/insights";
import { formatDate } from "@/lib/format";

type Params = { slug: string };

export function generateStaticParams() {
  return insights.map((a) => ({ slug: a.slug }));
}

export async function generateMetadata({ params }: { params: Promise<Params> }): Promise<Metadata> {
  const { slug } = await params;
  const a = insights.find((x) => x.slug === slug);
  return a ? { title: a.title, description: a.excerpt, openGraph: { type: "article", images: [a.cover.src] } } : {};
}

export default async function ArticlePage({ params }: { params: Promise<Params> }) {
  const { slug } = await params;
  const i = insights.findIndex((x) => x.slug === slug);
  if (i < 0) notFound();
  const a = insights[i];
  const next = insights[(i + 1) % insights.length];

  return (
    <article>
      <header data-nav-theme="dark" className="bg-ink px-gutter pb-14 pt-[26vh] text-paper md:pl-[clamp(140px,13vw,220px)]">
        <Link href="/insights" className="ui-label mb-8 inline-flex items-center gap-2 text-steel hover:text-brand">
          <Arrow className="rotate-180" /> All insights
        </Link>
        <p className="ui-label mb-6 flex gap-3 text-steel">
          <span className="text-brand">{a.tag}</span>
          <time dateTime={a.date}>{formatDate(a.date)}</time>· {a.read} read
        </p>
        <RevealHeading as="h1" lines={[a.title]} className="max-w-[22ch] text-[clamp(2rem,4.6vw,4.4rem)] leading-[0.95]" />
        <p className="mt-6 max-w-[56ch] text-[clamp(1rem,1.3vw,1.2rem)] leading-relaxed text-paper/70">{a.excerpt}</p>
      </header>

      <div data-nav-theme="light" className="bg-paper px-gutter pb-[14vh] md:pl-[clamp(140px,13vw,220px)]">
        <div className="relative -mt-px aspect-[21/9] w-full max-w-[1100px] overflow-hidden rounded-b-[2px] bg-ink/5">
          <Image src={a.cover.src} alt={a.cover.alt} fill priority sizes="(min-width: 1200px) 1100px, 100vw" className="object-cover" />
        </div>
        <div className="mt-14 max-w-[68ch] text-[17px] leading-[1.75] text-ink/85">
          {a.sections.map((s, k) => (
            <section key={k} className="mt-10 first:mt-0">
              {s.heading && <h2 className="display mb-4 text-[clamp(1.2rem,1.8vw,1.6rem)] leading-tight text-ink">{s.heading}</h2>}
              {s.paragraphs.map((p) => (
                <p key={p} className="mt-4 first:mt-0">
                  {p}
                </p>
              ))}
            </section>
          ))}
        </div>

        <div className="mt-16 flex max-w-[68ch] flex-wrap items-center gap-4 border-t border-ink/15 pt-10">
          <p className="mr-auto text-[15px] text-ink/70">Want us to look at your lanes?</p>
          <Button href="/contact#quote" variant="filled" tone="light">
            Request a quote
          </Button>
        </div>

        <Link href={`/insights/${next.slug}`} className="group mt-16 flex max-w-[1100px] flex-col gap-3 border-y border-ink/15 py-10">
          <span className="ui-label text-steel">Next article</span>
          <span className="display flex items-center gap-4 text-[clamp(1.4rem,2.6vw,2.4rem)] leading-tight transition-colors group-hover:text-brand">
            {next.title}
            <Arrow className="h-7 w-7 shrink-0 transition-transform duration-500 ease-expo group-hover:translate-x-2" />
          </span>
        </Link>
      </div>
    </article>
  );
}
