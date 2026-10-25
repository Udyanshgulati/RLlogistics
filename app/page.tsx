import { HomeSwitch } from "@/components/home/HomeSwitch";
import { MobileHome } from "@/components/mobile/MobileHome";
import { getAssetOverrides } from "@/lib/assets";

export default function HomePage() {
  // Real artwork dropped into /public/assets replaces the coded illustrations (checked at build time).
  const assets = getAssetOverrides();
  return (
    <HomeSwitch assets={assets}>
      <MobileHome assets={assets} />
    </HomeSwitch>
  );
}
