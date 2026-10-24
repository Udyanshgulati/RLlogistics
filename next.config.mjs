/** @type {import('next').NextConfig} */
const isExport = process.env.STATIC_EXPORT === "1";

const nextConfig = {
  reactStrictMode: true,
  transpilePackages: ["three"],
  // `STATIC_EXPORT=1 npm run build` writes a fully static site to /out for preview hosting.
  ...(isExport ? { output: "export", trailingSlash: true, assetPrefix: "/rl", images: { unoptimized: true }, generateBuildId: async () => "preview" } : {}),
};

export default nextConfig;
