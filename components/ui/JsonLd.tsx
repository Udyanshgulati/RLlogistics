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
  const data = [
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      name: company.legalName,
      alternateName: "RL Logistics",
      url: company.url,
      logo: `${company.url}/brand/rl-logo.png`,
      email: company.email,
      telephone: company.phone,
      foundingDate: company.incorporated,
      founder: company.directors.map((name) => ({ "@type": "Person", name })),
      address,
    },
    {
      "@context": "https://schema.org",
      "@type": "LocalBusiness",
      name: company.legalName,
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
