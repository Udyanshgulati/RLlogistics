import type { Metadata } from "next";
import { PageHero } from "@/components/ui/PageHero";
import { Icon } from "@/components/ui/Icon";
import { Button } from "@/components/ui/Button";
import { industries } from "@/content/industries";

export const metadata: Metadata = {
  alternates: { canonical: "/industries" },
  title: "Industries",
  description: "Logistics for FMCG, pharma, e-commerce, automotive, electronics and textiles — built around how each industry actually ships.",
};

export default function IndustriesPage() {
  return (
    <>
      <PageHero
        eyebrow="Industries"
        headline={["Built for", "how you ship."]}
        intro="Every industry moves differently. These are the ones we know best, and the specifics we've built around each of them."
      />
      <section data-nav-theme="light" className="bg-paper px-gutter py-[10vh] md:pl-[clamp(140px,13vw,220px)]">
        {industries.map((ind, i) => (
          <article key={ind.slug} id={ind.slug} className="grid scroll-mt-28 gap-8 border-t border-ink/15 py-14 md:grid-cols-12">
            <div className="flex items-start gap-5 md:col-span-5">
              <span className="ui-label pt-2 text-steel">{String(i + 1).padStart(2, "0")}</span>
              <div>
                <Icon name={ind.icon} className="mb-5 h-9 w-9" />
                <h2 className="display text-[clamp(1.8rem,3.4vw,3.2rem)] leading-[0.95]">{ind.title}</h2>
              </div>
            </div>
            <div className="md:col-span-7">
              <p className="max-w-[52ch] text-[clamp(1rem,1.3vw,1.2rem)] leading-relaxed text-ink/75">{ind.body}</p>
              <ul className="mt-6 flex flex-wrap gap-2">
                {ind.points.map((p) => (
                  <li key={p} className="ui-label rounded-[10px] border border-ink/15 px-4 py-2 text-ink/75">
                    {p}
                  </li>
                ))}
              </ul>
            </div>
          </article>
        ))}
        <div className="border-t border-ink/15 pt-12">
          <Button href="/contact" variant="filled" tone="light">
            Talk to an industry lead
          </Button>
        </div>
      </section>
    </>
  );
}
