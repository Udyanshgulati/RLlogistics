import type { IconName } from "@/components/ui/Icon";

export const industries: { slug: string; title: string; icon: IconName; body: string; points: string[] }[] = [
  {
    slug: "fmcg",
    title: "FMCG",
    icon: "fmcg",
    body: "High-velocity SKUs, tight shelf-life and a hundred distributors. We run the replenishment rhythm that keeps modern trade and kiranas stocked.",
    points: ["Primary and secondary distribution", "FEFO / batch control", "Distributor milk-runs across North India"],
  },
  {
    slug: "pharma",
    title: "Pharma & Healthcare",
    icon: "pharma",
    body: "GDP-aligned storage and 2–8 °C reefer movement with continuous data logging, so every excursion is caught and documented.",
    points: ["Temperature-mapped storage", "Reefer FTL with live loggers", "Narcotics & Schedule-H compliant handling"],
  },
  {
    slug: "ecommerce",
    title: "E-commerce & D2C",
    icon: "cart",
    body: "Marketplace and D2C fulfilment that scales from launch day to festive peak without changing your stack.",
    points: ["Same-day NCR, next-day metros", "COD reconciliation T+2", "Returns grading and restock"],
  },
  {
    slug: "automotive",
    title: "Automotive",
    icon: "car",
    body: "Inbound-to-line logistics for OEMs and tier-1 suppliers, with milk-runs, JIT sequencing and import clearance for CKD kits.",
    points: ["JIT / JIS line feeding", "CKD/SKD import clearance", "Returnable packaging loops"],
  },
  {
    slug: "electronics",
    title: "Electronics",
    icon: "chip",
    body: "High-value, theft-sensitive cargo handled in caged zones with serial-number tracking from port to retailer.",
    points: ["Serialised inventory", "Secure caged storage", "Air freight for launch windows"],
  },
  {
    slug: "textiles",
    title: "Textiles & Apparel",
    icon: "textile",
    body: "Export consolidation for garment clusters in NCR, Panipat and Ludhiana, plus GOH handling for domestic retail.",
    points: ["LCL consolidation to EU / US", "Garment-on-hanger handling", "Season-launch DC operations"],
  },
];
