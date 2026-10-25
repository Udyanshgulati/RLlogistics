import type { Metadata } from "next";
import { PageHero } from "@/components/ui/PageHero";
import { ContactForm } from "@/components/contact/ContactForm";
import { QuoteRequestForm } from "@/components/contact/QuoteRequestForm";
import { company } from "@/content/company";

export const metadata: Metadata = {
  title: "Contact",
  description: `Get a quote or talk to RL Logistics and Warehousing. ${company.address.street}, Delhi 110053. ${company.email}`,
};

export default function ContactPage() {
  return (
    <>
      <PageHero
        eyebrow="Contact"
        headline={["Let's talk", "freight."]}
        intro="Tell us what you're moving. You'll hear back from a person, not a ticket queue, within one working day."
        tone="light"
      />

      <section data-nav-theme="light" className="grid gap-16 bg-paper px-gutter pb-[14vh] md:grid-cols-12 md:pl-[clamp(140px,13vw,220px)]">
        <div className="md:col-span-7">
          <p className="eyebrow mb-8 text-steel">Send an enquiry</p>
          <ContactForm />
        </div>
        <aside className="flex flex-col gap-10 md:col-span-4 md:col-start-9">
          <div>
            <p className="ui-label mb-3 text-steel">Email</p>
            <a href={`mailto:${company.emailGeneral}`} className="select-all text-[18px] underline decoration-ink/25 underline-offset-4 hover:text-brand">
              {company.emailGeneral}
            </a>
            <a href={`mailto:${company.email}`} className="mt-1 block select-all text-[15px] text-ink/70 hover:text-brand">
              {company.email}
            </a>
          </div>
          <div>
            <p className="ui-label mb-3 text-steel">Phone</p>
            <a href={company.phoneHref} className="select-all text-[18px] hover:text-brand">
              {company.phone}
            </a>
            <p className="ui-label mt-3 text-steel">{company.hours}</p>
          </div>
          <div>
            <p className="ui-label mb-3 text-steel">Corporate office</p>
            <p className="leading-relaxed text-ink/75">{company.corporateOffice}</p>
            <p className="ui-label mb-3 mt-6 text-steel">Registered office</p>
            <address className="not-italic leading-relaxed text-ink/75">
              {company.legalName}
              {company.address.lines.map((l) => (
                <span key={l} className="block">
                  {l}
                </span>
              ))}
            </address>
            <p className="ui-label mt-3 text-steel">CIN {company.cin}</p>
          </div>
          <div>
            <p className="ui-label mb-3 text-steel">Branches</p>
            <ul className="flex flex-col gap-2 text-[14px] leading-snug text-ink/75">
              {company.branches.map((b) => (
                <li key={b.city}>
                  <span className="font-medium text-ink">{b.city}</span> · {b.area}
                </li>
              ))}
            </ul>
          </div>
        </aside>
      </section>

      <section id="quote" data-nav-theme="dark" className="scroll-mt-0 bg-ink px-gutter py-[14vh] text-paper md:pl-[clamp(140px,13vw,220px)]">
        <div className="grid gap-12 md:grid-cols-12">
          <div className="md:col-span-4">
            <p className="eyebrow mb-4 text-brand">Quick quote</p>
            <h2 className="display text-[clamp(2rem,4vw,3.8rem)] leading-[0.92]">Get an all-in quote.</h2>
            <p className="mt-5 max-w-[36ch] text-[14px] text-paper/60">Share the lane and the cargo. You get a written, all-in price (no hidden detention or “misc” lines) within one working day.</p>
          </div>
          <div className="md:col-span-7 md:col-start-6">
            <QuoteRequestForm />
          </div>
        </div>
      </section>

      <section data-nav-theme="light" aria-label="Map" className="relative h-[60vh] overflow-hidden bg-[#e6e5e0]">
        <svg aria-hidden className="absolute inset-0 h-full w-full" preserveAspectRatio="xMidYMid slice" viewBox="0 0 1200 600">
          <rect width="1200" height="600" fill="#e6e5e0" />
          {[...Array(14)].map((_, i) => (
            <path key={i} d={`M${-100 + i * 110} 0 Q ${80 + i * 100} 300 ${-60 + i * 115} 600`} stroke="#d4d3cd" strokeWidth={i % 4 === 0 ? 14 : 5} fill="none" />
          ))}
          {[...Array(8)].map((_, i) => (
            <path key={i} d={`M0 ${40 + i * 80} Q 600 ${10 + i * 85} 1200 ${60 + i * 75}`} stroke="#d4d3cd" strokeWidth={i % 3 === 0 ? 12 : 4} fill="none" />
          ))}
          <path d="M0 470 C 300 420, 520 520, 1200 380" stroke="#b9c9e6" strokeWidth={26} fill="none" />
        </svg>
        <div className="absolute left-1/2 top-1/2 flex -translate-x-1/2 -translate-y-full flex-col items-center">
          <div className="rounded-[4px] bg-ink px-4 py-3 text-paper shadow-xl">
            <p className="ui-label text-brand">RL Logistics · Registered office</p>
            <p className="mt-1 text-[13px]">Bhajanpura, Delhi 110053</p>
          </div>
          <span className="h-6 w-px bg-ink" />
          <span className="h-3 w-3 rounded-full bg-brand ring-4 ring-brand/30" />
        </div>
        <a
          href="https://www.google.com/maps/search/?api=1&query=Bhajanpura+Delhi+110053"
          target="_blank"
          rel="noreferrer"
          className="ui-label absolute bottom-6 right-gutter rounded-full bg-paper px-4 py-2 shadow hover:text-brand"
        >
          Open in Google Maps ↗
        </a>
      </section>
    </>
  );
}
