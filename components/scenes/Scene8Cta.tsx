import { cta } from "@/content/home";
import { company } from "@/content/company";
import { GhostHeading } from "@/components/text/GhostHeading";
import { MagneticButton } from "@/components/ui/MagneticButton";

/** SCENE 8 — the closing call to action. */
export function Scene8Cta() {
  return (
    <section data-scene="cta" data-nav-theme="light" className="relative overflow-hidden bg-paper px-gutter py-[22vh] md:pl-[clamp(140px,13vw,220px)]" aria-labelledby="cta-title">
      <p className="eyebrow mb-6 text-steel">05 — Next shipment</p>
      <GhostHeading as="h2" lines={cta.headline} className="text-[clamp(3rem,10vw,10.5rem)] leading-[0.88]" />
      <span id="cta-title" className="sr-only">
        {cta.headline.join(" ")}
      </span>
      <div className="mt-12 flex flex-col gap-12 md:flex-row md:items-center md:justify-between">
        <div>
          <p className="max-w-[46ch] text-[15px] leading-relaxed text-ink/65">{cta.sub}</p>
          <p className="ui-label mt-6 text-steel">
            Or write to <span className="select-all text-ink">{company.email}</span>
          </p>
        </div>
        <div className="md:mr-[6vw]">
          <MagneticButton href={cta.button.href}>{cta.button.label}</MagneticButton>
        </div>
      </div>
    </section>
  );
}
