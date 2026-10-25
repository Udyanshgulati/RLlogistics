import { company } from "@/content/company";

/**
 * There is no backend yet: enquiries are handed to the visitor's mail app, addressed to the company.
 * Returns a mailto: link with the fields laid out one per line.
 */
export function enquiryMailto(subject: string, fields: Record<string, string | undefined>, note?: string) {
  const body = [
    ...Object.entries(fields).map(([k, v]) => `${k}: ${v?.trim() || "—"}`),
    ...(note?.trim() ? ["", note.trim()] : []),
  ].join("\n");
  return `mailto:${company.email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
}

export const isEmail = (v = "") => /^\S+@\S+\.\S+$/.test(v);
export const isPhone = (v = "") => v.replace(/\D/g, "").length >= 10;
