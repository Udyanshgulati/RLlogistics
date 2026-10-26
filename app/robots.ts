import type { MetadataRoute } from "next";
import { company } from "@/content/company";

export const dynamic = "force-static";

export default function robots(): MetadataRoute.Robots {
  // Staging/preview deployments (e.g. the client subdomain) set NEXT_PUBLIC_NOINDEX=1 so search engines
  // don't index a copy of the live site (rllogisticsindia.com).
  if (process.env.NEXT_PUBLIC_NOINDEX === "1") return { rules: [{ userAgent: "*", disallow: "/" }] };
  return { rules: [{ userAgent: "*", allow: "/", disallow: "/playground" }], sitemap: `${company.url}/sitemap.xml` };
}
