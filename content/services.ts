import type { IconName } from "@/components/ui/Icon";

export type Service = {
  slug: string;
  index: string;
  title: string;
  short: string;
  icon: IconName;
  hero: string;
  intro: string;
  capabilities: { title: string; body: string }[];
  facts: { value: string; label: string }[]; // DUMMY figures
  lanes: string[];
};

export const services: Service[] = [
  {
    slug: "warehousing-3pl",
    index: "01",
    title: "Warehousing & 3PL",
    short: "Bonded and non-bonded space across Delhi NCR with WMS-led inventory, kitting and returns.",
    icon: "warehouse",
    hero: "Space that thinks like a supply chain.",
    intro:
      "Racked, temperature-aware warehousing in the NCR belt, run on a live WMS. We receive, store, pick, kit and dispatch to your SLAs, and you can see every SKU on your own dashboard.",
    capabilities: [
      { title: "Inbound & putaway", body: "Dock scheduling, quality checks and barcode putaway inside 24 hours of arrival." },
      { title: "Pick, pack & kitting", body: "Batch and wave picking, co-packing, promo kits and labelling for retail chains." },
      { title: "Bonded storage", body: "Defer duty on imported stock and clear it in tranches as demand lands." },
      { title: "Reverse logistics", body: "Returns inspection, grading, refurbishment routing and vendor RTV." },
    ],
    facts: [
      { value: "1.2L sq ft", label: "Managed space, NCR" }, // DUMMY
      { value: "99.7%", label: "Inventory accuracy" }, // DUMMY
      { value: "24h", label: "Dock-to-stock" }, // DUMMY
    ],
    lanes: ["Bhiwandi", "Kundli", "Farukhnagar", "Tughlakabad ICD"],
  },
  {
    slug: "road-transport",
    index: "02",
    title: "Road Transport (FTL/PTL)",
    short: "Full and part truckloads on every major corridor, GPS-tracked from dock to door.",
    icon: "truck",
    hero: "Every corridor. One control tower.",
    intro:
      "Our own and partner fleets run 14 ft to 40 ft containers, open bodies and reefers. Every vehicle reports to one control tower, and every exception is flagged before you have to ask.",
    capabilities: [
      { title: "Full truckload", body: "Dedicated vehicles on Delhi–Mumbai, Delhi–Kolkata, Delhi–Chennai and Delhi–Bengaluru." },
      { title: "Part truckload", body: "Consolidated loads with milk-runs across North India and fixed weekly departures." },
      { title: "Container haulage", body: "Port and ICD drayage between JNPT, Mundra, Tughlakabad and Patparganj." },
      { title: "Dedicated fleet", body: "Branded vehicles and drivers on contract for high-frequency lanes." },
    ],
    facts: [
      { value: "650+", label: "Vehicles on network" }, // DUMMY
      { value: "28", label: "States covered" }, // DUMMY
      { value: "98.2%", label: "On-time delivery" }, // DUMMY
    ],
    lanes: ["Delhi → Mumbai", "Delhi → Kolkata", "Delhi → Chennai", "Delhi → Bengaluru"],
  },
  {
    slug: "freight-forwarding",
    index: "03",
    title: "Freight Forwarding",
    short: "Air and ocean freight with carrier contracts out of DEL, BOM, MAA, JNPT, Mundra and Chennai.",
    icon: "ship",
    hero: "Air when it's urgent. Ocean when it's smart.",
    intro:
      "FCL, LCL and air cargo on contracted space, with origin pickup, export documentation and destination delivery under one airway bill or bill of lading.",
    capabilities: [
      { title: "Ocean FCL / LCL", body: "Weekly consolidation out of JNPT and Mundra to the Gulf, SE Asia, Europe and the US." },
      { title: "Air freight", body: "Express, priority and deferred options with ULD and BUP handling at IGI Delhi." },
      { title: "Sea–air", body: "Hybrid routing via Dubai and Singapore that cuts transit time without paying the full air premium." },
      { title: "DG & special cargo", body: "IATA DGR-certified handling for hazardous, oversized and high-value freight." },
    ],
    facts: [
      { value: "40+", label: "Countries served" }, // DUMMY
      { value: "1,800", label: "TEUs a year" }, // DUMMY
      { value: "6", label: "Gateway ports" }, // DUMMY
    ],
    lanes: ["JNPT → Jebel Ali", "Mundra → Singapore", "DEL → FRA", "MAA → Port Klang"],
  },
  {
    slug: "customs-clearance",
    index: "04",
    title: "Customs Clearance",
    short: "Licensed CHA filings, duty planning and ICEGATE compliance, so cargo doesn't wait at the gate.",
    icon: "customs",
    hero: "Cleared before it lands.",
    intro:
      "Our customs desk files bills of entry and shipping bills in advance and handles the HS classification, valuation and FTA certificates that keep duty correct and audits clean.",
    capabilities: [
      { title: "Import & export filing", body: "Advance BE and SB filing on ICEGATE, with first-check and assessment support." },
      { title: "Duty optimisation", body: "FTA benefits, MOOWR, EPCG and Advance Authorisation planning." },
      { title: "Compliance & audit", body: "Classification reviews, valuation disputes and post-clearance audit readiness." },
      { title: "AEO facilitation", body: "Documentation and process support for AEO-T1/T2 accreditation." },
    ],
    facts: [
      { value: "< 24h", label: "Average clearance" }, // DUMMY
      { value: "12k+", label: "Filings a year" }, // DUMMY
      { value: "0", label: "Penalties in FY25" }, // DUMMY
    ],
    lanes: ["ICD Tughlakabad", "ICD Patparganj", "IGI Air Cargo", "JNPT / Nhava Sheva"],
  },
  {
    slug: "last-mile-fulfilment",
    index: "05",
    title: "Last-Mile & E-commerce",
    short: "Same-day and next-day delivery across NCR with COD, live ETAs and photo proof of delivery.",
    icon: "parcel",
    hero: "The last mile is the one they remember.",
    intro:
      "Marketplace and D2C fulfilment that plugs straight into your storefront. Orders drop in, get picked inside the hour and leave for doorsteps across NCR the same day.",
    capabilities: [
      { title: "Marketplace integration", body: "Plug-and-play with major storefronts and OMS platforms through API or flat-file." },
      { title: "Same / next day", body: "Hyperlocal riders in NCR and next-day coverage to 120+ cities." }, // DUMMY
      { title: "COD & reconciliation", body: "Cash remittance in T+2 with a ledger you can audit line by line." },
      { title: "Proof of delivery", body: "Geo-tagged photo POD and OTP confirmation pushed to you in minutes." },
    ],
    facts: [
      { value: "18k", label: "Orders a day, peak" }, // DUMMY
      { value: "4h", label: "NCR same-day window" }, // DUMMY
      { value: "1.1%", label: "RTO rate" }, // DUMMY
    ],
    lanes: ["Delhi", "Gurugram", "Noida", "Faridabad"],
  },
  {
    slug: "project-cargo",
    index: "06",
    title: "Project Cargo",
    short: "Oversized, heavy-lift and multi-modal moves engineered end to end, from route survey to final placement.",
    icon: "crane",
    hero: "If it doesn't fit, we engineer the route.",
    intro:
      "Transformers, turbines, plant machinery. We survey the route, get the permits, plan the lift and escort the load. One project manager owns it from origin to foundation.",
    capabilities: [
      { title: "Route engineering", body: "Route surveys, bridge load checks, utility lifts and escort planning." },
      { title: "ODC transport", body: "Hydraulic axles, low-beds and multi-axle trailers up to 400 MT." }, // DUMMY
      { title: "Lifting & rigging", body: "Crane selection, lift plans and certified riggers on site." },
      { title: "Breakbulk & charter", body: "Vessel chartering and breakbulk handling through Kandla and Mundra." },
    ],
    facts: [
      { value: "400 MT", label: "Heaviest single lift" }, // DUMMY
      { value: "60+", label: "Projects delivered" }, // DUMMY
      { value: "0", label: "Lost-time incidents" }, // DUMMY
    ],
    lanes: ["Kandla", "Mundra", "Vizag", "Haldia"],
  },
];

/** The homepage services row — air and ocean are split out for the story. */
export const homeServices: { title: string; body: string; icon: IconName; href: string }[] = [
  { title: "Warehousing & 3PL", body: "WMS-led storage, kitting and returns across Delhi NCR.", icon: "warehouse", href: "/services/warehousing-3pl" },
  { title: "Road Transport", body: "FTL and PTL on every major corridor, GPS-tracked.", icon: "truck", href: "/services/road-transport" },
  { title: "Air Freight", body: "Express and deferred options out of IGI Delhi and BOM.", icon: "plane", href: "/services/freight-forwarding" },
  { title: "Ocean Freight", body: "FCL and LCL from JNPT, Mundra and Chennai.", icon: "ship", href: "/services/freight-forwarding" },
  { title: "Customs Clearance", body: "Licensed CHA filings and duty planning on ICEGATE.", icon: "customs", href: "/services/customs-clearance" },
  { title: "Last-Mile Delivery", body: "Same-day NCR delivery with live ETAs and photo POD.", icon: "parcel", href: "/services/last-mile-fulfilment" },
];
