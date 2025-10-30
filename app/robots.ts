// app/robots.ts
import type { MetadataRoute } from "next";

// 👉 wichtig für output: "export"
export const dynamic = "force-static";

export default function robots(): MetadataRoute.Robots {
  const base = "https://jozapf.de";
  return {
    rules: { userAgent: "*", allow: "/" },
    sitemap: `${base}/sitemap.xml`,
    host: "jozapf.de",
  };
}
