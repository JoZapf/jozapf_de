// app/en/pflichtpraktikum-anwendungsentwicklung-berlin/page.tsx
import fs from "node:fs";
import path from "node:path";
import type { Metadata } from "next";

export const dynamic = "force-static";

/**
 * SEO Metadata – Mandatory Internship Application Development (EN)
 *
 * Keyword strategy:
 * - Primary:   mandatory internship application development Berlin
 * - Secondary: IT specialist FIAE IHK, DevOps internship, system integration
 * - Long-tail: software development internship Berlin 2026, IT internship Docker CI/CD
 * - Branding:  Jo Zapf
 *
 * Optimised: 2025-02-16
 */
export const metadata: Metadata = {
  title: "Mandatory Internship Application Development Berlin 2026 – Jo Zapf | FIAE (IHK)",
  description:
    "Mandatory internship in application development (FIAE/IHK) in Berlin from June 2026. " +
    "960 hours · DevOps, Docker, CI/CD, Python, Java, system integration, " +
    "zero-trust infrastructure · 20+ years of cross-media and IT experience.",
  keywords: [
    // Primary
    "mandatory internship",
    "application development",
    "Berlin",
    // Qualification
    "IT specialist",
    "FIAE",
    "IHK",
    "vocational training",
    "Fachinformatiker",
    // Technologies
    "Python",
    "Java",
    "Docker",
    "Docker Compose",
    "CI/CD",
    "GitHub Actions",
    "Shell",
    "Bash",
    // DevOps & infrastructure
    "DevOps",
    "system integration",
    "automation",
    "zero trust",
    "least privilege",
    "defense-in-depth",
    "Linux",
    "Windows",
    // Context
    "software development",
    "IT internship",
    "cross-media",
    "web development",
    "internship 2026",
  ],
  alternates: {
    canonical: "/en/pflichtpraktikum-anwendungsentwicklung-berlin/",
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
    url: "https://jozapf.de/en/pflichtpraktikum-anwendungsentwicklung-berlin/",
    title: "Mandatory Internship Application Development – Berlin 2026",
    description:
      "FIAE mandatory internship (IHK) from June 2026 in Berlin. " +
      "DevOps, system integration, Docker, CI/CD, Python, Java " +
      "– backed by 20+ years of cross-media and IT practice.",
    locale: "en_US",
    alternateLocale: ["de_DE"],
    images: [
      {
        url: "https://assets.jozapf.de/og/og-praktikum-en.png",
        secureUrl: "https://assets.jozapf.de/og/og-praktikum-en.png",
        width: 1200,
        height: 630,
        alt: "Jo Zapf – Mandatory Internship Application Development Berlin",
        type: "image/png",
      },
    ],
  },
  twitter: {
    card: "summary_large_image",
    title: "Mandatory Internship Application Development – Jo Zapf | Berlin 2026",
    description:
      "960-hour FIAE internship (IHK) from June 2026. " +
      "DevOps, Docker, CI/CD, Python, Java, system integration & zero-trust infrastructure.",
    images: ["https://assets.jozapf.de/og/og-praktikum-en.png"],
  },
};

function readFragment(name: string) {
  const filePath = path.join(process.cwd(), "app", "en", name);
  if (!fs.existsSync(filePath)) {
    throw new Error(`Fragment "${name}" not found at: ${filePath}`);
  }
  return fs.readFileSync(filePath, "utf8");
}

export default function InternshipPage() {
  const header = readFragment("header-fragment.html");
  const main   = readFragment("internship-fragment.html");
  const footer = readFragment("footer-fragment.html");

  return (
    <>
      <div dangerouslySetInnerHTML={{ __html: header }} />
      <main id="main-content" className="page-praktikum" dangerouslySetInnerHTML={{ __html: main }} />
      <div dangerouslySetInnerHTML={{ __html: footer }} />
    </>
  );
}
