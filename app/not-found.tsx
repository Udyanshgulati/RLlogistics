import { Button } from "@/components/ui/Button";

export default function NotFound() {
  return (
    <section data-nav-theme="dark" className="flex min-h-[100svh] flex-col justify-end bg-ink px-gutter pb-[12vh] text-paper md:pl-[clamp(140px,13vw,220px)]">
      <p className="eyebrow mb-6 text-brand">Error 404 · Consignment not found</p>
      <h1 className="display text-[clamp(3rem,11vw,11rem)] leading-[0.88]">
        Wrong
        <br />
        turn.
      </h1>
      <p className="mt-8 max-w-[44ch] text-paper/70">This page took a detour. The shipment you&apos;re looking for may have moved, or the link is out of date.</p>
      <div className="mt-8 flex gap-3">
        <Button href="/" variant="filled" tone="dark">
          Back home
        </Button>
        <Button href="/contact" variant="ghost" tone="dark">
          Contact us
        </Button>
      </div>
    </section>
  );
}
