// app/pflichtpraktikum-anwendungsentwicklung-berlin/page.tsx
import fs from "node:fs";
import path from "node:path";
import type { Metadata } from "next";

export const dynamic = "force-static";

/**
 * SEO Metadata – Pflichtpraktikum Anwendungsentwicklung (DE)
 *
 * Keyword-Strategie:
 * - Primary:   Pflichtpraktikum Anwendungsentwicklung Berlin
 * - Secondary: Fachinformatiker FIAE IHK, DevOps Praktikum, Systemintegration
 * - Long-tail: Praktikum Softwareentwicklung Berlin 2026, IT-Praktikum Docker CI/CD
 * - Branding:  Jo Zapf
 *
 * Optimiert: 2025-02-16
 */
export const metadata: Metadata = {
  title: "Pflichtpraktikum Anwendungsentwicklung Berlin 2026 – Jo Zapf | FIAE (IHK)",
  description:
    "Pflichtpraktikum Anwendungsentwicklung (FIAE/IHK) in Berlin ab Juni 2026. " +
    "960 Stunden · DevOps, Docker, CI/CD, Python, Java, Systemintegration, " +
    "Zero-Trust-Infrastruktur · Über 20 Jahre Cross-Media- und IT-Erfahrung.",
  keywords: [
    // Primary
    "Pflichtpraktikum",
    "Anwendungsentwicklung",
    "Berlin",
    // Ausbildung / Qualifikation
    "Fachinformatiker",
    "FIAE",
    "IHK",
    "Umschulung",
    "Betriebliche Erprobung",
    // Technologien
    "Python",
    "Java",
    "Docker",
    "Docker Compose",
    "CI/CD",
    "GitHub Actions",
    "Shell",
    "Bash",
    // DevOps & Infrastruktur
    "DevOps",
    "Systemintegration",
    "Automation",
    "Zero Trust",
    "Least Privilege",
    "Defense-in-Depth",
    "Linux",
    "Windows",
    // Kontext
    "Softwareentwicklung",
    "IT-Praktikum",
    "Cross-Media",
    "Webentwicklung",
    "Praktikum 2026",
  ],
  alternates: {
    canonical: "/pflichtpraktikum-anwendungsentwicklung-berlin/",
    languages: {
      de: "https://jozapf.de/pflichtpraktikum-anwendungsentwicklung-berlin/",
      en: "https://jozapf.de/en/pflichtpraktikum-anwendungsentwicklung-berlin/",
      "x-default": "https://jozapf.de/pflichtpraktikum-anwendungsentwicklung-berlin/",
    },
  },
  robots: {
    index: true,
    follow: true,
    googleBot:
      "index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1",
  },
  openGraph: {
    type: "website",
    siteName: "Jo Zapf",
    url: "https://jozapf.de/pflichtpraktikum-anwendungsentwicklung-berlin/",
    title: "Pflichtpraktikum Anwendungsentwicklung – Berlin 2026",
    description:
      "FIAE-Pflichtpraktikum (IHK) ab Juni 2026 in Berlin. " +
      "DevOps, Systemintegration, Docker, CI/CD, Python, Java " +
      "– gestützt durch über 20 Jahre Cross-Media- und IT-Praxis.",
    locale: "de_DE",
    alternateLocale: ["en_US"],
    images: [
      {
        url: "https://assets.jozapf.de/og/og-home-de.png",
        secureUrl: "https://assets.jozapf.de/og/og-home-de.png",
        width: 1200,
        height: 630,
        alt: "Jo Zapf – Pflichtpraktikum Anwendungsentwicklung Berlin",
        type: "image/png",
      },
    ],
  },
  twitter: {
    card: "summary_large_image",
    title: "Pflichtpraktikum Anwendungsentwicklung – Jo Zapf | Berlin 2026",
    description:
      "960 Stunden FIAE-Praktikum (IHK) ab Juni 2026. " +
      "DevOps, Docker, CI/CD, Python, Java, Systemintegration & Zero-Trust-Infrastruktur.",
    images: ["https://assets.jozapf.de/og/og-home-de.png"],
  },
};

function readFragment(name: string) {
  const filePath = path.join(process.cwd(), "app", name);
  if (!fs.existsSync(filePath)) {
    throw new Error(`Fragment "${name}" nicht gefunden: ${filePath}`);
  }
  return fs.readFileSync(filePath, "utf8");
}

export default function PraktikumPage() {
  const header = readFragment("header-fragment.html");
  const main   = readFragment("praktikum-fragment.html");
  const footer = readFragment("footer-fragment.html");

  return (
    <>
      <div dangerouslySetInnerHTML={{ __html: header }} />
      <main id="main-content" className="page-praktikum" dangerouslySetInnerHTML={{ __html: main }} />
      <div dangerouslySetInnerHTML={{ __html: footer }} />
    </>
  );
}
