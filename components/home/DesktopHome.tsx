"use client";

import { Scene1Hero } from "@/components/scenes/Scene1Hero";
import { Scene2About } from "@/components/scenes/Scene2About";
import { Scene3Yard } from "@/components/scenes/Scene3Yard";
import { Scene5Road } from "@/components/scenes/Scene5Road";
import { Scene6Ocean } from "@/components/scenes/Scene6Ocean";
import { Scene7Trust } from "@/components/scenes/Scene7Trust";
import { Scene8Cta } from "@/components/scenes/Scene8Cta";
import type { AssetOverrides } from "@/lib/asset-slots";

/** The cinematic desktop journey (≥ 1024px), unchanged. */
export default function DesktopHome({ assets }: { assets: AssetOverrides }) {
  return (
    <>
      <Scene1Hero />
      <Scene2About assets={assets} />
      {/* Scene 3 (yard) also carries Scene 4 (services) inside its dark panel. */}
      <Scene3Yard assets={assets} />
      <Scene5Road assets={assets} />
      <Scene6Ocean assets={assets} />
      <Scene7Trust assets={assets} />
      <Scene8Cta />
    </>
  );
}
