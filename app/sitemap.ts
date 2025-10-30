// app/sitemap.ts
import type { MetadataRoute } from "next";

// 👉 wichtig für output: "export"
export const dynamic = "force-static";

export default function sitemap(): MetadataRoute.Sitemap {
  const base = "https://jozapf.de";
  const now = new Date().toISOString(); // wird beim Build ausgewertet
  return [
    { url: `${base}/`, lastModified: now, changeFrequency: "monthly", priority: 1 },
    { url: `${base}/privacy.html`, lastModified: now, changeFrequency: "yearly", priority: 0.3 },
  ];
}
