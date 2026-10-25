// Real company data — keep exact.
export const company = {
  name: "RL Logistics",
  nameSub: "& Warehousing",
  legalName: "RL Logistics and Warehousing Private Limited",
  cin: "U74999DL2018PTC333219",
  incorporated: "2018-04-27",
  incorporatedLabel: "27 April 2018",
  roc: "RoC Delhi",
  directors: ["Randhir Kumar Jha", "Rekha Jha"],
  // Official mailboxes: info = enquiries and quotes (the default), support = live shipments and
  // existing customers, ceo = escalations and leadership.
  email: "info@rllogisticsindia.com",
  emailSupport: "support@rllogisticsindia.com",
  emailCeo: "ceo@rllogisticsindia.com",
  phone: "+91 98102 62947",
  phoneHref: "tel:+919810262947",
  hours: "Office 9:00 AM – 6:00 PM · service 24 hours, 365 days a year",
  corporateOffice: "D-114, Street No. 4, Bhajanpura, Delhi – 110053",
  branches: [
    { city: "Mumbai", area: "Temghar, Bhiwandi (MH) 421302" },
    { city: "Pune", area: "Ranjangaon, Pune–Nagar Road (MH) 412209" },
    { city: "Bengaluru", area: "Madanayakanahalli, Tumkur Road (KA) 562162" },
    { city: "Kolkata", area: "Alampur, Howrah (WB) 711302" },
  ],
  address: {
    lines: ["D-228, G/F, Kh. No. 448, Gali No. 7", "Bhajanpura, Near Kali Mata Mandir", "Delhi – 110053, India"],
    street: "D-228, G/F, Kh. No. 448, Gali No. 7, Bhajanpura, Near Kali Mata Mandir",
    locality: "Delhi",
    postalCode: "110053",
    country: "IN",
  },
  // Canonical address for SEO (sitemap, OG, JSON-LD). Override per deployment with NEXT_PUBLIC_SITE_URL.
  url: process.env.NEXT_PUBLIC_SITE_URL ?? "https://rllogistics.in",
} as const;
