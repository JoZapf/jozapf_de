// app/sitemap.ts
import type { MetadataRoute } from "next";

// 👉 wichtig für output: "export"
export const dynamic = "force-static";

export default function sitemap(): MetadataRoute.Sitemap {
  const base = "https://jozapf.de";
  const now = new Date().toISOString(); // wird beim Build ausgewertet
  return [
    // DE
    { url: `${base}/`,                                              lastModified: now, changeFrequency: "monthly",  priority: 1   },
    { url: `${base}/pflichtpraktikum-anwendungsentwicklung-berlin/`, lastModified: now, changeFrequency: "monthly",  priority: 0.9 },
    // EN
    { url: `${base}/en/`,                                           lastModified: now, changeFrequency: "monthly",  priority: 0.9 },
    { url: `${base}/en/pflichtpraktikum-anwendungsentwicklung-berlin/`, lastModified: now, changeFrequency: "monthly", priority: 0.8 },
    // Meta
    { url: `${base}/privacy.html`,                                  lastModified: now, changeFrequency: "yearly",   priority: 0.3 },
  ];
}
