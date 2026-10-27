import type { Metadata, Viewport } from "next";
import { IBM_Plex_Mono, Manrope, Sora } from "next/font/google";
import "./globals.css";
import { LenisProvider } from "@/lib/lenis";
import { LoaderProvider } from "@/lib/loader";
import { Cursor } from "@/components/ui/Cursor";
import { Nav } from "@/components/ui/Nav";
import { Footer } from "@/components/ui/Footer";
import { JsonLd } from "@/components/ui/JsonLd";
import { company } from "@/content/company";

// RL type system: Sora for headlines (sentence case), Manrope for text, IBM Plex Mono for labels and codes.
const display = Sora({ subsets: ["latin"], variable: "--font-display", display: "swap", weight: ["600", "700", "800"] });
const body = Manrope({ subsets: ["latin"], variable: "--font-body", display: "swap" });
const mono = IBM_Plex_Mono({ subsets: ["latin"], variable: "--font-mono", display: "swap", weight: ["400", "500"] });

export const metadata: Metadata = {
  metadataBase: new URL(company.url),
  title: {
    default: `${company.name} & Warehousing | Logistics Company in Delhi — Warehousing, Transport & Customs`,
    template: `%s | ${company.name} & Warehousing, Delhi`,
  },
  description:
    "RL Logistics & Warehousing is a Delhi logistics company: warehousing & 3PL, FTL/PTL road transport, freight forwarding and customs clearance across India, run by one accountable team since 2018.",
  keywords: [
    "logistics company in Delhi",
    "warehousing in Delhi NCR",
    "3PL Delhi",
    "road transport Delhi",
    "FTL PTL trucking India",
    "freight forwarding Delhi",
    "customs clearance ICD Tughlakabad",
    "RL Logistics",
  ],
  alternates: { canonical: "/" },
  applicationName: "RL Logistics & Warehousing",
  publisher: company.legalName,
  // Website credit: the studio that designed and built the site.
  creator: "Thoda Logic",
  // Site credits (humans.txt) — renders <meta name="author"> + <link rel="author">.
  authors: [{ name: "Thoda Logic", url: "/humans.txt" }],
  other: { designer: "Thoda Logic — https://thodalogic.com" },
  category: "logistics",
  openGraph: { type: "website", locale: "en_IN", siteName: "RL Logistics & Warehousing", url: "/" },
  ...(process.env.NEXT_PUBLIC_NOINDEX === "1" ? { robots: { index: false, follow: false } } : {}),
  twitter: { card: "summary_large_image" },
};

export const viewport: Viewport = { themeColor: "#0B1F4D", width: "device-width", initialScale: 1, viewportFit: "cover" };

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en-IN" className={`${display.variable} ${body.variable} ${mono.variable}`}>
      <body>
        <JsonLd />
        <a href="#main" className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[200] focus:rounded focus:bg-brand focus:px-3 focus:py-2 focus:text-ink">
          Skip to content
        </a>
        <LoaderProvider>
          <LenisProvider>
            <Cursor />
            <Nav />
            <main id="main">{children}</main>
            <Footer />
          </LenisProvider>
        </LoaderProvider>
      </body>
    </html>
  );
}
