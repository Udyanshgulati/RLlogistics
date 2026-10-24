import type { MetadataRoute } from "next";
import { company } from "@/content/company";
import { services } from "@/content/services";

export const dynamic = "force-static";

export default function sitemap(): MetadataRoute.Sitemap {
  const routes = ["", "/about", "/services", "/industries", "/network", "/insights", "/careers", "/contact", ...services.map((s) => `/services/${s.slug}`)];
  return routes.map((r) => ({ url: `${company.url}${r}`, changeFrequency: "monthly", priority: r === "" ? 1 : 0.7 }));
}
