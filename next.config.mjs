/** @type {import('next').NextConfig} */

// Build modes:
//   npm run build                → normal Next.js server build (`npm start`, Vercel)
//   SITE_EXPORT=1 npm run build  → plain static site in /out for any web server (nginx on the subdomain)
//   STATIC_EXPORT=1 npm run build → static site with assets under /rl, only for the hosted preview
const isPreview = process.env.STATIC_EXPORT === "1";
const isSite = process.env.SITE_EXPORT === "1";

const exportBase = { output: "export", trailingSlash: true, images: { unoptimized: true } };

const nextConfig = {
  reactStrictMode: true,
  transpilePackages: ["three"],
  ...(isSite ? exportBase : {}),
  ...(isPreview ? { ...exportBase, assetPrefix: "/rl", generateBuildId: async () => "preview" } : {}),
};

export default nextConfig;
