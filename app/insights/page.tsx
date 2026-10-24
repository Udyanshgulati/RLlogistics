import type { Metadata } from "next";
import { PageHero } from "@/components/ui/PageHero";
import { insights } from "@/content/insights";

export const metadata: Metadata = {
  title: "Insights",
  description: "Practical notes from the RL Logistics floor on e-commerce peaks, customs clearance and cold-chain shipping in India.",
};

const fmt = (d: string) => new Date(d).toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" });

export default function InsightsPage() {
  return (
    <>
      <PageHero eyebrow="Insights" headline={["Notes from", "the floor."]} intro="What we've learned moving freight across India, written by the people who run the operation." />
      <section data-nav-theme="light" className="bg-paper px-gutter py-[12vh] md:pl-[clamp(140px,13vw,220px)]">
        {insights.map((a) => (
          <article key={a.slug} id={a.slug} className="grid scroll-mt-28 gap-6 border-t border-ink/15 py-14 md:grid-cols-12">
            <div className="md:col-span-4">
              <p className="ui-label text-brand">{a.tag}</p>
              <p className="ui-label mt-2 text-steel">
                <time dateTime={a.date}>{fmt(a.date)}</time> · {a.read} read
              </p>
            </div>
            <div className="md:col-span-8">
              <h2 className="display max-w-[24ch] text-[clamp(1.5rem,2.8vw,2.6rem)] leading-[0.98]">{a.title}</h2>
              <p className="mt-4 max-w-[60ch] text-[16px] leading-relaxed text-ink/70">{a.excerpt}</p>
              <details className="group mt-6 max-w-[64ch]">
                <summary className="ui-label inline-flex cursor-pointer list-none items-center gap-2 border-b border-ink/25 pb-1 hover:border-brand hover:text-brand [&::-webkit-details-marker]:hidden">
                  <span className="group-open:hidden">Read the article</span>
                  <span className="hidden group-open:inline">Close</span>
                </summary>
                <div className="mt-6 flex flex-col gap-4 text-[16px] leading-relaxed text-ink/80">
                  {a.body.map((p) => (
                    <p key={p}>{p}</p>
                  ))}
                </div>
              </details>
            </div>
          </article>
        ))}
      </section>
    </>
  );
}
