import { RevealHeading } from "@/components/text/RevealHeading";

/** Inner-page opener: eyebrow, big masked headline, intro. Dark by default to echo the homepage hero. */
export function PageHero({
  eyebrow,
  headline,
  intro,
  tone = "dark",
  children,
}: {
  eyebrow: string;
  headline: readonly string[];
  intro?: string;
  tone?: "dark" | "light";
  children?: React.ReactNode;
}) {
  const dark = tone === "dark";
  return (
    <section
      data-nav-theme={tone}
      className={`relative overflow-hidden px-gutter pb-[10vh] pt-[30vh] md:pl-[clamp(140px,13vw,220px)] ${dark ? "bg-ink text-paper" : "bg-paper text-ink"}`}
    >
      <div aria-hidden className={`pointer-events-none absolute -right-[10vw] -top-[20vw] h-[60vw] w-[60vw] rounded-full blur-3xl ${dark ? "bg-[radial-gradient(circle,rgba(255,90,31,0.22),transparent_60%)]" : "bg-[radial-gradient(circle,rgba(30,107,255,0.12),transparent_60%)]"}`} />
      <p className="eyebrow relative mb-6 flex items-center gap-3 text-brand">
        <span aria-hidden className="h-px w-6 bg-brand" />
        {eyebrow}
      </p>
      <RevealHeading as="h1" lines={headline} className="relative text-[clamp(2.8rem,8vw,8.5rem)] leading-[0.9] tracking-[-0.02em]" />
      {intro && <p className={`relative mt-8 max-w-[52ch] text-[clamp(1rem,1.3vw,1.2rem)] leading-relaxed ${dark ? "text-paper/70" : "text-ink/70"}`}>{intro}</p>}
      {children}
    </section>
  );
}
