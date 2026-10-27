import type { Metadata } from "next";
import { PageHero } from "@/components/ui/PageHero";
import { IndiaMap } from "@/components/network/IndiaMap";
import { networkStats } from "@/content/network";

export const metadata: Metadata = {
  alternates: { canonical: "/network" },
  title: "Network",
  description: "RL Logistics' pan-India network: Delhi NCR hub, port gateways at JNPT, Mundra, Chennai and Kolkata, ICD Tughlakabad and partner hubs.",
};

export default function NetworkPage() {
  return (
    <>
      <PageHero
        eyebrow="Network"
        headline={["Delhi", "to everywhere."]}
        intro="One control tower in Delhi, gateways on both coasts and partner hubs in every major region. Hover a hub to see what runs through it."
      />
      <section data-nav-theme="dark" className="bg-ink px-gutter pb-[14vh] text-paper md:pl-[clamp(140px,13vw,220px)]">
        <IndiaMap />
        <dl className="mt-16 grid grid-cols-2 gap-8 border-t border-paper/10 pt-10 md:grid-cols-4">
          {networkStats.map((s) => (
            <div key={s.label}>
              <dd className="display text-[clamp(2rem,4vw,3.6rem)] leading-none">{s.value}</dd>
              <dt className="mt-2 text-[13px] text-paper/60">{s.label}</dt>
            </div>
          ))}
        </dl>
      </section>
    </>
  );
}
