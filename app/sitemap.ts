import type { MetadataRoute } from "next";
import { company } from "@/content/company";
import { services } from "@/content/services";
import { insights } from "@/content/insights";

export const dynamic = "force-static";

export default function sitemap(): MetadataRoute.Sitemap {
  const routes = ["", "/about", "/services", "/industries", "/network", "/insights", "/contact", ...services.map((s) => `/services/${s.slug}`), ...insights.map((a) => `/insights/${a.slug}`)];
  // Trailing slash matches the exported pages' canonical URLs (trailingSlash: true in the static export).
  const slash = process.env.SITE_EXPORT === "1" || process.env.STATIC_EXPORT === "1" ? "/" : "";
  return routes.map((r) => ({ url: `${company.url}${r}${r ? slash : "/"}`, changeFrequency: "monthly", priority: r === "" ? 1 : 0.7 }));
}
