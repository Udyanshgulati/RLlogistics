import { company } from "@/content/company";

/** Organization + LocalBusiness structured data with the registered address. */
export function JsonLd() {
  const address = {
    "@type": "PostalAddress",
    streetAddress: company.address.street,
    addressLocality: company.address.locality,
    postalCode: company.address.postalCode,
    addressCountry: company.address.country,
  };
  // The studio that designed and built the site (credited as the website's creator).
  const studio = { "@type": "Organization", "@id": "https://thodalogic.com/#organization", name: "Thoda Logic", url: "https://thodalogic.com/" };
  const data = [
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      "@id": `${company.url}/#website`,
      name: "RL Logistics & Warehousing",
      url: company.url,
      inLanguage: "en-IN",
      publisher: { "@id": `${company.url}/#organization` },
      creator: studio,
    },
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "@id": `${company.url}/#organization`,
      name: company.legalName,
      alternateName: "RL Logistics",
      url: company.url,
      logo: `${company.url}/brand/rl-logo.png`,
      email: company.email,
      telephone: company.phone,
      foundingDate: company.incorporated,
      founder: company.directors.map((name) => ({ "@type": "Person", name })),
      contactPoint: [
        { "@type": "ContactPoint", contactType: "sales", email: company.email, telephone: company.phone, areaServed: "IN" },
        { "@type": "ContactPoint", contactType: "customer support", email: company.emailSupport, telephone: company.phone, areaServed: "IN" },
      ],
      address,
    },
    {
      "@context": "https://schema.org",
      "@type": "LocalBusiness",
      "@id": `${company.url}/#localbusiness`,
      name: company.legalName,
      description: "Logistics company in Delhi: warehousing & 3PL, road transport, freight forwarding and customs clearance across India.",
      openingHours: "Mo-Sa 09:00-18:00",
      priceRange: "₹₹",
      geo: { "@type": "GeoCoordinates", latitude: 28.7041, longitude: 77.2613 },
      url: company.url,
      email: company.email,
      telephone: company.phone,
      image: `${company.url}/brand/rl-logo.png`,
      address,
      areaServed: "IN",
    },
  ];
  return <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(data) }} />;
}
