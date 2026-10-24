// Homepage copy. Headlines are arrays of lines — each line is masked separately.

export const hero = {
  eyebrow: "One operator · Since 2018",
  headline: ["Every mile.", "Every", "milestone."],
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
  eyebrow: "01 — Who we are",
  headline: ["We move freight.", "We own", "the outcome."],
  paragraphs: [
    "Warehousing, trucking, forwarding and customs under one roof, with one team answering for every consignment from the dock in Tughlakabad to the shelf in Bengaluru.",
    "No finger-pointing between vendors. No cargo lost in handoffs. One plan, one tracker, one phone number — and a team that treats your delivery date like its own.",
  ],
  link: { label: "Learn more about us", href: "/about" },
  imageAlt: "Aerial view of a highway interchange",
};
