import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: "export",
  outputFileTracingRoot: process.cwd(),
  basePath: "/gamification-kpi",
  assetPrefix: "/gamification-kpi",
  trailingSlash: true,
};

export default nextConfig;
