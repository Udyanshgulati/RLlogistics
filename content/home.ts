// Homepage copy. Headlines are arrays of lines — each line is masked separately.

export const hero = {
  eyebrow: "One operator · Since 2018",
  headline: ["From Delhi", "to every", "doorstep."],
  sub: "Warehousing, road transport, freight forwarding and customs — run by one accountable team out of Delhi, moving cargo across India.",
  primary: { label: "Our services", href: "/services" },
  secondary: { label: "Get a quote", href: "/contact#quote" },
  scrollHint: "Scroll to begin the journey",
  coords: "28.61° N · 77.21° E",
};

/** Globe markers. Delhi is the hub; arcs run from Delhi to the rest. */
export const globeMarkers = [
  { id: "delhi", label: "Delhi", sub: "HQ · Hub", lat: 28.61, lng: 77.21, hub: true, side: "left" },
  { id: "mumbai", label: "Mumbai", sub: "JNPT", lat: 19.08, lng: 72.88, side: "left" },
  { id: "chennai", label: "Chennai", sub: "Port", lat: 13.08, lng: 80.27, side: "right" },
  { id: "kolkata", label: "Kolkata", sub: "Port", lat: 22.57, lng: 88.36, side: "right" },
  { id: "dubai", label: "Dubai", sub: "Jebel Ali", lat: 25.2, lng: 55.27, side: "left" },
  { id: "singapore", label: "Singapore", sub: "Transhipment", lat: 1.35, lng: 103.82, side: "right" },
] as const;

/** Where the globe rests on load (centre of India) before the dive swings Delhi to the middle. */
export const globeRest = { lat: 21, lng: 80 };

export const about = {
  eyebrow: "RLWU 01 · Who we are",
  headline: ["We own every", "shipment,", "end to end."],
  paragraphs: [
    "Warehousing, trucking, forwarding and customs under one roof, with one team answering for every consignment from the dock in Tughlakabad to the shelf in Bengaluru.",
    "No finger-pointing between vendors. No cargo lost in handoffs. One plan, one tracker, one phone number — and a team that treats your delivery date like its own.",
  ],
  link: { label: "Learn more about us", href: "/about" },
  imageAlt: "Aerial view of a highway interchange",
};

export const servicesSection = {
  eyebrow: "RLWU 02 · What we do",
  headline: ["Warehouse to", "last mile.", "One team."],
  paragraphs: [
    "From the bonded warehouse in Tughlakabad to the last doorstep in Gurugram, every leg runs on our own processes, our own control tower and our own people.",
    "One contract, one tracker and one invoice, whether the cargo moves by truck, vessel or freighter.",
  ],
  watermark: "Our services",
  cta: { label: "All services", href: "/services" },
};

export const road = {
  eyebrow: "RLWU 03 · How we deliver",
  headline: ["Tracked", "every", "kilometre."],
  milestones: [
    { icon: "gps", title: "Real-time GPS tracking", body: "Every vehicle pings the control tower every 60 seconds. You see what we see." },
    { icon: "user", title: "Dedicated account manager", body: "One named person who knows your SKUs, your docks and your deadlines." },
    { icon: "tower", title: "24×7 control tower", body: "Exceptions get flagged and fixed at 3 a.m., long before they reach your inbox." },
    { icon: "pod", title: "Proof of delivery in minutes", body: "Geo-tagged photo POD and e-signature are pushed to you as the truck pulls away." },
    { icon: "thermo", title: "Temperature-controlled options", body: "Reefer trucks and 2–8 °C storage for pharma and perishables, with data loggers." },
  ],
} as const;

export const ocean = {
  headline: ["Port to shelf,", "one partner."],
  features: [
    { icon: "contact", title: "One point of contact", body: "No call-centre roulette. One team owns your account across every mode." },
    { icon: "eye", title: "Full supply-chain visibility", body: "PO to POD on one dashboard: container, truck, shelf and doorstep." },
    { icon: "shield", title: "Compliance you can trust", body: "Licensed CHA, ICEGATE filings and audit-ready paperwork, every time." },
    { icon: "rupee", title: "Transparent, competitive pricing", body: "All-in quotes with no surprise detention, demurrage or 'misc' lines." },
  ],
} as const;

export const trust = {
  eyebrow: "RLWU 04 · Clients",
  headline: ["Chosen by", "brands that", "can't wait."],
  intro: "From first-time importers to national retail chains, our clients stay because we treat their cargo like our own.",
};

export const cta = {
  headline: ["Ready when", "your cargo is."],
  sub: "Tell us what you're shipping, where it's going and when it needs to get there. We'll come back with a plan and an all-in quote within one working day.",
  button: { label: "Get a quote", href: "/contact#quote" },
};
