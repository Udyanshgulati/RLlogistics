"use client";

import dynamic from "next/dynamic";
import { useEffect, useState } from "react";
import type { AssetOverrides } from "@/lib/asset-slots";

const DESKTOP = "(min-width: 1024px)";

// Holds the screen dark while the desktop scenes load, so the footer never flashes up first.
const Placeholder = () => <div aria-hidden className="hidden h-[100svh] bg-ink lg:block" />;

// Loaded only on desktop: phones never download, mount or create ScrollTriggers for the desktop scenes.
const DesktopHome = dynamic(() => import("./DesktopHome"), { ssr: false, loading: Placeholder });

/**
 * Desktop (≥ 1024px) gets the cinematic scenes; phones and tablets get the server-rendered <MobileHome>
 * passed in as children (hidden with CSS on desktop, and its animations are gated to < 1024px).
 */
export function HomeSwitch({ assets, children }: { assets: AssetOverrides; children: React.ReactNode }) {
  const [desktop, setDesktop] = useState<boolean | null>(null);

  useEffect(() => {
    const mq = window.matchMedia(DESKTOP);
    const update = () => setDesktop(mq.matches);
    update();
    mq.addEventListener("change", update);
    return () => mq.removeEventListener("change", update);
  }, []);

  return (
    <>
      {desktop === null && <Placeholder />}
      {desktop && <DesktopHome assets={assets} />}
      <div className="lg:hidden">{children}</div>
    </>
  );
}
