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
  photo: { src: string; alt: string };
};

export const services: Service[] = [
  {
    slug: "warehousing-3pl",
    photo: { src: "/photos/warehouse.webp", alt: "Palletised stock in a warehouse" },
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
      { title: "Inventory & materials management", body: "End-to-end replenishment planning, spares and materials control, and milk-run deliveries on fixed routes." },
    ],
    facts: [
      { value: "7", label: "Cities with warehouse branches" },
      { value: "99.6%", label: "Inventory accuracy (cycle counts)" }, // DUMMY
      { value: "24h", label: "Dock-to-stock" }, // DUMMY
    ],
    lanes: ["Delhi", "Mumbai", "Chennai", "Kolkata", "Ahmedabad", "Hyderabad", "Aurangabad"],
  },
  {
    slug: "road-transport",
    photo: { src: "/photos/highway-truck.webp", alt: "Truck on a highway at night" },
    index: "02",
    title: "Road Transport (FTL/PTL)",
    short: "Full and part truckloads on every major corridor, GPS-tracked from dock to door.",
    icon: "truck",
    hero: "Every corridor. One control tower.",
    intro:
      "Our own and partner fleets run 14 ft to 40 ft containers, open bodies and reefers. Every vehicle reports to one control tower, and every exception is flagged before you have to ask.",
    capabilities: [
      { title: "Primary movement, FTL", body: "Dedicated, sealed vehicles with no transhipment on the way, GPS on every truck and MIS in the format you need." },
      { title: "Secondary distribution", body: "Warehouse-to-dealer distribution on dedicated milk-run routes, so stock keeps flowing to every outlet." },
      { title: "Express FTL", body: "Company-owned containerised vehicles moving non-stop: Delhi NCR to Mumbai in 36 hours, Kolkata in 60, Chennai in 72." },
      { title: "Containers & ODC", body: "20 ft and 40 ft import/export containers, steel, refractories and over-dimensional loads, with a route survey first." },
    ],
    facts: [
      { value: "1000+", label: "Vehicles on the network" },
      { value: "36 h", label: "Delhi NCR → Mumbai, express" },
      { value: "60 h", label: "Delhi → Kolkata, express" },
    ],
    lanes: ["Delhi → Mumbai (NH-48)", "Delhi → Kolkata (NH-19)", "Delhi → Chennai", "Delhi → Bengaluru", "Delhi → Pune"],
  },
  {
    slug: "freight-forwarding",
    photo: { src: "/photos/port-cranes.webp", alt: "Container ship under gantry cranes at a port" },
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
      { value: "30+", label: "Countries served" }, // DUMMY
      { value: "1,200+", label: "TEUs a year" }, // DUMMY
      { value: "6", label: "Gateway ports" }, // DUMMY
    ],
    lanes: ["Nhava Sheva (JNPT) → Jebel Ali", "Mundra → Singapore", "Chennai → Port Klang", "DEL → FRA (air)", "DEL → DXB (air)"],
  },
  {
    slug: "customs-clearance",
    photo: { src: "/photos/container-stack.webp", alt: "Stacked shipping containers" },
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
      { value: "< 36h", label: "Average clearance, ICD" }, // DUMMY
      { value: "4,500+", label: "Bills of entry & shipping bills a year" }, // DUMMY
      { value: "0", label: "Penalties in FY 2024-25" }, // DUMMY
    ],
    lanes: ["ICD Tughlakabad", "ICD Patparganj", "ICD Dadri", "Air Cargo Complex, IGI Delhi", "Nhava Sheva (JNPT)"],
  },
  {
    slug: "last-mile-fulfilment",
    photo: { src: "/photos/fulfilment.webp", alt: "Parcels moving through a fulfilment centre" },
    index: "05",
    title: "Last-Mile & E-commerce",
    short: "Same-day and next-day delivery across NCR with COD, live ETAs and photo proof of delivery.",
    icon: "parcel",
    hero: "The last mile is the one they remember.",
    intro:
      "Marketplace and D2C fulfilment that plugs straight into your storefront. Orders drop in, get picked inside the hour and leave for doorsteps across NCR the same day.",
    capabilities: [
      { title: "Marketplace integration", body: "Plug-and-play with major storefronts and OMS platforms through API or flat-file." },
      { title: "Same / next day", body: "Own riders across Delhi NCR and next-day delivery to 60+ cities through partner networks." }, // DUMMY
      { title: "COD & reconciliation", body: "Cash remittance in T+2 with a ledger you can audit line by line." },
      { title: "Proof of delivery", body: "Geo-tagged photo POD and OTP confirmation pushed to you in minutes." },
    ],
    facts: [
      { value: "6,000", label: "Orders a day at festive peak" }, // DUMMY
      { value: "Same day", label: "Within Delhi NCR" }, // DUMMY
      { value: "94%", label: "First-attempt delivery" }, // DUMMY
    ],
    lanes: ["Delhi", "Gurugram", "Noida", "Ghaziabad", "Faridabad"],
  },
  {
    slug: "project-cargo",
    photo: { src: "/photos/container-terminal.webp", alt: "Ship-to-shore cranes at a container terminal" },
    index: "06",
    title: "Project Cargo",
    short: "Oversized, heavy-lift and multi-modal moves engineered end to end, from route survey to final placement.",
    icon: "crane",
    hero: "If it doesn't fit, we engineer the route.",
    intro:
      "Transformers, turbines, plant machinery. We survey the route, get the permits, plan the lift and escort the load. One project manager owns it from origin to foundation.",
    capabilities: [
      { title: "Route survey first", body: "Our technical team surveys the route and writes a feasibility report: weak bridges, overhead lines, turning radii and where extra pullers are needed." },
      { title: "ODC transport", body: "Hydraulic axles, low-beds and multi-axle trailers for loads up to 120 MT." }, // DUMMY
      { title: "Lifting & rigging", body: "Crane selection, lift plans and certified riggers on site." },
      { title: "Breakbulk & charter", body: "Vessel chartering and breakbulk handling through Kandla and Mundra." },
    ],
    facts: [
      { value: "120 MT", label: "Heaviest single move" }, // DUMMY
      { value: "40+", label: "Projects delivered" }, // DUMMY
      { value: "0", label: "Lost-time incidents" }, // DUMMY
    ],
    lanes: ["Deendayal (Kandla)", "Mundra", "Visakhapatnam", "Haldia"],
  },
];

/** The homepage services row — air and ocean are split out for the story. */
export const homeServices: { title: string; body: string; icon: IconName; href: string }[] = [
  { title: "Warehousing & 3PL", body: "WMS-led storage, kitting and returns across Delhi NCR.", icon: "warehouse", href: "/services/warehousing-3pl" },
  { title: "Road Transport", body: "FTL and PTL on every major corridor out of Delhi, GPS-tracked.", icon: "truck", href: "/services/road-transport" },
  { title: "Air Freight", body: "Express and deferred options out of IGI Delhi and BOM.", icon: "plane", href: "/services/freight-forwarding" },
  { title: "Ocean Freight", body: "FCL and LCL from JNPT, Mundra and Chennai.", icon: "ship", href: "/services/freight-forwarding" },
  { title: "Customs Clearance", body: "Licensed CHA filings and duty planning on ICEGATE.", icon: "customs", href: "/services/customs-clearance" },
  { title: "Last-Mile Delivery", body: "Same-day NCR delivery with live ETAs and photo POD.", icon: "parcel", href: "/services/last-mile-fulfilment" },
];
