import type { Metadata } from "next";
import Image from "next/image";
import { PageHero } from "@/components/ui/PageHero";
import { RevealHeading } from "@/components/text/RevealHeading";
import { ScrubParagraph } from "@/components/text/ScrubParagraph";
import { Button } from "@/components/ui/Button";
import { aboutPage } from "@/content/about";
import { company } from "@/content/company";

export const metadata: Metadata = {
  title: "About",
  description: "RL Logistics and Warehousing: an owner-run Delhi logistics company since 2018, running warehousing, transport, forwarding and customs under one roof.",
};

export default function AboutPage() {
  return (
    <>
      <PageHero eyebrow={aboutPage.eyebrow} headline={aboutPage.headline} intro={aboutPage.intro} />

      <section data-nav-theme="light" className="grid gap-16 bg-paper px-gutter py-[16vh] md:grid-cols-12 md:pl-[clamp(140px,13vw,220px)]">
        <p className="eyebrow text-steel md:col-span-3">Our story</p>
        <div className="flex flex-col gap-8 md:col-span-8">
          {aboutPage.story.map((p) => (
            <ScrubParagraph key={p} className="max-w-[44ch] text-[clamp(1.15rem,1.8vw,1.7rem)] leading-snug">
              {p}
            </ScrubParagraph>
          ))}
        </div>
      </section>

      <section data-nav-theme="light" aria-label="Our operations" className="grid gap-4 bg-paper px-gutter pb-[14vh] sm:grid-cols-5 md:pl-[clamp(140px,13vw,220px)]">
        <figure className="relative aspect-[4/3] overflow-hidden rounded-[2px] bg-ink/5 sm:col-span-3">
          <Image src="/photos/warehouse-floor.webp" alt="Warehouse floor seen from above" fill sizes="(min-width: 640px) 55vw, 100vw" className="object-cover" />
        </figure>
        <figure className="relative aspect-[4/3] overflow-hidden rounded-[2px] bg-ink/5 sm:col-span-2 sm:aspect-auto">
          <Image src="/photos/office-planning.webp" alt="Team planning a shipment schedule at a desk" fill sizes="(min-width: 640px) 35vw, 100vw" className="object-cover" />
        </figure>
      </section>

      <section data-nav-theme="dark" className="bg-ink px-gutter py-[16vh] text-paper md:pl-[clamp(140px,13vw,220px)]">
        <p className="eyebrow mb-8 text-brand">Mission</p>
        <p className="display max-w-[26ch] text-[clamp(1.7rem,3.3vw,3.4rem)] leading-[1]">{aboutPage.mission}</p>
        <p className="eyebrow mb-4 mt-16 text-steel">Vision</p>
        <p className="max-w-[44ch] text-[clamp(1.1rem,1.6vw,1.5rem)] leading-snug text-paper/75">{aboutPage.vision}</p>
      </section>

      <section data-nav-theme="light" className="bg-paper px-gutter py-[16vh] md:pl-[clamp(140px,13vw,220px)]">
        <RevealHeading lines={["Leadership"]} className="mb-14 text-[clamp(2.2rem,5vw,5rem)]" />
        <ul className="grid gap-6 sm:grid-cols-2 lg:max-w-[900px]">
          {aboutPage.leadership.map((l) => (
            <li key={l.name} className="group border-t border-ink/15 pt-6">
              <div
                aria-hidden
                className="display mb-6 flex aspect-square w-full max-w-[280px] items-center justify-center bg-ink text-[clamp(3rem,7vw,6rem)] text-paper transition-colors duration-500 group-hover:bg-brand group-hover:text-ink"
              >
                {l.initials}
              </div>
              <p className="display text-[20px]">{l.name}</p>
              <p className="ui-label mt-1 text-steel">{l.role}</p>
              <p className="mt-3 max-w-[36ch] text-[14px] text-ink/65">{l.note}</p>
            </li>
          ))}
        </ul>
        <p className="mt-12 text-[15px] text-ink/70">
          Something we should hear at the top? Write to the CEO&apos;s office:{" "}
          <a href={`mailto:${company.emailCeo}`} className="inline-flex min-h-11 items-center text-ink underline decoration-ink/30 underline-offset-4 hover:text-brand md:min-h-0">
            {company.emailCeo}
          </a>
        </p>
      </section>

      <section data-nav-theme="light" className="border-t border-ink/10 bg-paper px-gutter py-[16vh] md:pl-[clamp(140px,13vw,220px)]">
        <RevealHeading lines={["How we work"]} className="mb-14 text-[clamp(2.2rem,5vw,5rem)]" />
        <ul className="grid gap-x-10 gap-y-12 sm:grid-cols-2 lg:grid-cols-4">
          {aboutPage.values.map((v, i) => (
            <li key={v.title} className="border-t border-ink/15 pt-5">
              <span className="ui-label text-steel">{String(i + 1).padStart(2, "0")}</span>
              <h3 className="display mt-4 text-[18px] leading-tight">{v.title}</h3>
              <p className="mt-3 text-[14px] leading-relaxed text-ink/65">{v.body}</p>
            </li>
          ))}
        </ul>
      </section>

      <section data-nav-theme="dark" className="bg-ink px-gutter py-[16vh] text-paper md:pl-[clamp(140px,13vw,220px)]">
        <RevealHeading lines={["Since 2018"]} className="mb-14 text-[clamp(2.2rem,5vw,5rem)]" />
        <ol className="relative border-l border-paper/15">
          {aboutPage.timeline.map((t) => (
            <li key={t.year} className="relative grid gap-2 pb-12 pl-8 md:grid-cols-[160px_1fr]">
              <span aria-hidden className="absolute -left-[5px] top-2 h-[9px] w-[9px] rounded-full bg-brand" />
              <span className="display text-[clamp(1.6rem,3vw,2.6rem)] leading-none">{t.year}</span>
              <p className="max-w-[52ch] text-[15px] leading-relaxed text-paper/70">{t.text}</p>
            </li>
          ))}
        </ol>
        <dl className="mt-10 grid gap-6 border-t border-paper/10 pt-10 text-[13px] text-paper/60 sm:grid-cols-3">
          <div>
            <dt className="ui-label text-steel">Legal name</dt>
            <dd className="mt-2">{company.legalName}</dd>
          </div>
          <div>
            <dt className="ui-label text-steel">CIN</dt>
            <dd className="mt-2">{company.cin}</dd>
          </div>
          <div>
            <dt className="ui-label text-steel">Incorporated</dt>
            <dd className="mt-2">
              {company.incorporatedLabel} · {company.roc}
            </dd>
          </div>
        </dl>
        <div className="mt-12">
          <Button href="/contact" variant="brand">
            Work with us
          </Button>
        </div>
      </section>
    </>
  );
}
