import type { Metadata } from "next";
import { PageHero } from "@/components/ui/PageHero";
import { company } from "@/content/company";
import { careers } from "@/content/careers";

export const metadata: Metadata = { title: "Careers", description: "Open roles at RL Logistics and Warehousing in Delhi NCR." };

export default function CareersPage() {
  return (
    <>
      <PageHero eyebrow={careers.eyebrow} headline={careers.headline} intro={careers.intro} />
      <section data-nav-theme="light" className="grid gap-16 bg-paper px-gutter py-[12vh] md:grid-cols-12 md:pl-[clamp(140px,13vw,220px)]">
        <div className="md:col-span-4">
          <p className="eyebrow mb-6 text-steel">Why RL</p>
          <ul className="flex flex-col gap-3 text-[15px]">
            {careers.perks.map((p) => (
              <li key={p} className="flex gap-3">
                <span aria-hidden className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-brand" />
                {p}
              </li>
            ))}
          </ul>
        </div>
        <div className="md:col-span-8">
          <p className="eyebrow mb-6 text-steel">{careers.openings.length} open roles</p>
          <ul className="border-t border-ink/15">
            {careers.openings.map((o) => (
              <li key={o.title} className="border-b border-ink/15">
                <details className="group py-6">
                  <summary className="flex cursor-pointer list-none flex-col gap-2 md:flex-row md:items-center md:justify-between [&::-webkit-details-marker]:hidden">
                    <span className="display text-[clamp(1.2rem,2vw,1.7rem)] leading-tight transition-colors group-hover:text-brand">{o.title}</span>
                    <span className="ui-label flex items-center gap-4 text-steel">
                      {o.team} · {o.location}
                      <span aria-hidden className="text-[18px] leading-none transition-transform group-open:rotate-45">
                        +
                      </span>
                    </span>
                  </summary>
                  <div className="mt-4 max-w-[60ch] text-[15px] leading-relaxed text-ink/70">
                    <p className="ui-label mb-3 text-steel">{o.type}</p>
                    <p>{o.body}</p>
                    <p className="mt-4">
                      Apply: send your CV to <span className="select-all font-medium text-ink">{company.email}</span> with “{o.title}” in the subject.
                    </p>
                  </div>
                </details>
              </li>
            ))}
          </ul>
        </div>
      </section>
    </>
  );
}
