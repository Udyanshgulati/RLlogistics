import type { Metadata, Viewport } from "next";
import { Archivo, Inter, JetBrains_Mono } from "next/font/google";
import "./globals.css";
import { LenisProvider } from "@/lib/lenis";
import { LoaderProvider } from "@/lib/loader";
import { Cursor } from "@/components/ui/Cursor";
import { Nav } from "@/components/ui/Nav";
import { Footer } from "@/components/ui/Footer";
import { JsonLd } from "@/components/ui/JsonLd";
import { company } from "@/content/company";

const display = Archivo({ subsets: ["latin"], axes: ["wdth"], variable: "--font-display", display: "swap" });
const body = Inter({ subsets: ["latin"], variable: "--font-body", display: "swap" });
const mono = JetBrains_Mono({ subsets: ["latin"], variable: "--font-mono", display: "swap", weight: ["400", "500"] });

export const metadata: Metadata = {
  metadataBase: new URL(company.url),
  title: { default: `${company.name} & Warehousing — Every mile. Every milestone.`, template: `%s — ${company.name}` },
  description:
    "Warehousing, road transport, freight forwarding and customs clearance — run by one accountable team out of Delhi, moving cargo across India.",
  openGraph: { type: "website", locale: "en_IN", siteName: "RL Logistics & Warehousing" },
  ...(process.env.NEXT_PUBLIC_NOINDEX === "1" ? { robots: { index: false, follow: false } } : {}),
  twitter: { card: "summary_large_image" },
};

export const viewport: Viewport = { themeColor: "#0A0D14", width: "device-width", initialScale: 1 };

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
