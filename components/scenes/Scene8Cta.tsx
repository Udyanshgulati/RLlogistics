import { cta } from "@/content/home";
import { company } from "@/content/company";
import { GhostHeading } from "@/components/text/GhostHeading";
import { Button } from "@/components/ui/Button";
import { MagneticButton } from "@/components/ui/MagneticButton";

/** SCENE 8 — the closing call to action. */
export function Scene8Cta() {
  return (
    <section data-scene="cta" data-nav-theme="light" className="relative overflow-hidden bg-paper px-gutter py-[14vh] lg:py-[22vh] lg:pl-[clamp(140px,13vw,220px)]" aria-labelledby="cta-title">
      <p className="eyebrow mb-6 text-steel">05 — Next shipment</p>
      <GhostHeading as="h2" lines={cta.headline} className="text-[clamp(2.5rem,12vw,5.5rem)] leading-[0.92] lg:text-[clamp(3rem,10vw,10.5rem)] lg:leading-[0.88]" />
      <span id="cta-title" className="sr-only">
        {cta.headline.join(" ")}
      </span>
      <div className="mt-8 flex flex-col gap-8 lg:mt-12 lg:gap-12 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <p className="max-w-[46ch] text-[15px] leading-relaxed text-ink/65">{cta.sub}</p>
          <p className="ui-label mt-6 text-steel">
            Or write to{" "}
            <a href={`mailto:${company.email}`} className="select-all text-ink underline-offset-4 max-lg:inline-flex max-lg:min-h-11 max-lg:items-center max-lg:underline">
              {company.email}
            </a>
          </p>
        </div>
        {/* Touch screens: a plain full-width button instead of the magnetic circle. */}
        <Button href={cta.button.href} variant="brand" tone="light" className="h-14 w-full justify-between px-6 text-[15px] lg:hidden">
          {cta.button.label}
        </Button>
        <div className="hidden lg:mr-[6vw] lg:block">
          <MagneticButton href={cta.button.href}>{cta.button.label}</MagneticButton>
        </div>
      </div>
    </section>
  );
}
