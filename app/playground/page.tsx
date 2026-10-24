import type { Metadata } from "next";
import { RevealHeading } from "@/components/text/RevealHeading";
import { GhostHeading } from "@/components/text/GhostHeading";
import { ScrubParagraph } from "@/components/text/ScrubParagraph";
import { Counter } from "@/components/text/Counter";
import { Marquee } from "@/components/text/Marquee";
import { playground } from "@/content/playground";
import { stats } from "@/content/stats";

export const metadata: Metadata = { title: "Playground", robots: { index: false } };

function Block({ index, name, children, dark }: { index: string; name: string; children: React.ReactNode; dark?: boolean }) {
  return (
    <section data-nav-theme={dark ? "dark" : "light"} className={`relative border-t px-gutter py-[18vh] md:pl-[clamp(140px,13vw,220px)] ${dark ? "border-paper/10 bg-ink text-paper" : "border-ink/10"}`}>
      <p className="ui-label mb-10 text-steel">
        {index} / {name}
      </p>
      {children}
    </section>
  );
}

export default function PlaygroundPage() {
  return (
    <div className="bg-paper pt-40">
      <h1 className="display px-gutter pb-16 text-display-md md:pl-[clamp(140px,13vw,220px)]">{playground.title}</h1>

      <Block index="01" name="RevealHeading">
        <RevealHeading lines={playground.reveal} mutedLines={[1]} className="text-display-lg" />
      </Block>

      <Block index="02" name="GhostHeading" dark>
        <GhostHeading lines={playground.ghost} mutedLines={[2]} className="text-display-lg" />
      </Block>

      <Block index="02b" name="GhostHeading · scrubbed">
        <GhostHeading lines={playground.ghost} mode="scrub" className="text-display-md" />
      </Block>

      <Block index="03" name="ScrubParagraph">
        <ScrubParagraph className="max-w-[38ch] text-[clamp(1.25rem,2.2vw,2rem)] leading-snug">{playground.scrub}</ScrubParagraph>
      </Block>

      <Block index="04" name="Counter">
        <div className="grid gap-12 md:grid-cols-3">
          {stats.map((s) => (
            <div key={s.label}>
              <Counter value={s.value} decimals={s.decimals} suffix={s.suffix} className="text-[clamp(3rem,6vw,5.5rem)]" />
              <p className="mt-3 text-sm">{s.label}</p>
              <p className="text-xs text-steel">{s.sub}</p>
            </div>
          ))}
        </div>
      </Block>

      <Block index="05" name="Marquee watermark" dark>
        <div className="relative h-[60vh]">
          <Marquee text={playground.marquee} className="absolute inset-x-0 top-1/2 -translate-y-1/2 text-paper/15" />
          <p className="relative max-w-[40ch] pt-[20vh] text-paper/70">
            The watermark drifts at 0.3× scroll speed behind the content.
          </p>
        </div>
      </Block>
      <div className="h-[50vh]" />
    </div>
  );
}
