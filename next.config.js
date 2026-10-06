/** @type {import('next').NextConfig} */
const nextConfig = {
  typescript: { ignoreBuildErrors: true },
  async rewrites() {
    return [
      {
        source: "/:path*",
        destination: "https://saradeeeb.com/:path*",
      },
    ];
  },
};
module.exports = nextConfig;
