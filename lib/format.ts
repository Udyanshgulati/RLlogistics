/** "12 Sept 2026" style dates (en-IN). */
export const formatDate = (d: string) => new Date(d).toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" });
