import { Scene1Hero } from "@/components/scenes/Scene1Hero";
import { about } from "@/content/home";

export default function HomePage() {
  return (
    <>
      <Scene1Hero />
      {/* Scene 2 lands here next — this paper block receives the dive's white haze. */}
      <section data-nav-theme="light" className="relative flex min-h-screen items-end bg-paper px-gutter pb-24 md:pl-[clamp(140px,13vw,220px)]">
        <div>
          <p className="eyebrow mb-4 text-steel">{about.eyebrow} · Scene 2 in progress</p>
          <h2 className="display text-display-lg text-steel/40">
            {about.headline.map((l) => (
              <span key={l} className="block">
                {l}
              </span>
            ))}
          </h2>
        </div>
      </section>
    </>
  );
}
