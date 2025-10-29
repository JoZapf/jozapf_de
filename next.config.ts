// next.config.ts
import type { NextConfig } from 'next';

// Eigenes Konfig-Typ-Mapping: NextConfig + custom Feld
type AppNextConfig = NextConfig & {
  allowedDevOrigins: string[];
};

/**
 * Ziele:
 * - Static Export nach `out/` (CI/Deploy erwartet `out/index.html`)
 * - `next/image` ohne Server → unoptimized
 * - trailingSlash = true (Apache/Shared Hosting)
 * - Custom-Feld `allowedDevOrigins` bleibt nutzbar (eigener Typ oben)
 */
const nextConfig: AppNextConfig = {
  // 1) Statischer Export
  output: 'export',

  // 2) Image-Optimizer aus (für reines Static Hosting)
  images: { unoptimized: true },

  // 3) Ordner-Style-URLs
  trailingSlash: true,

  // 4) Strenger React-Modus (DX)
  reactStrictMode: true,

  // 5) Projektspezifisches Feld (von Next ignoriert, von eigener App nutzbar)
  allowedDevOrigins: ['localhost', '127.0.0.1', '100.127.178.114'],
};

export default nextConfig;
