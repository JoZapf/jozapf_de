// app/en/layout.tsx
import type { Metadata } from "next";
import "../globals.css";

/**
 * English version metadata
 * Optimiert: 2025-12-03
 * - og:locale: en_US
 * - Vollständige englische Metadaten
 * - Konsistent mit deutscher Version
 */
export const metadata: Metadata = {
  title: "Jo Zapf – Web Development & Application Development | Berlin",
  description:
    "Web development and application development from Berlin. " +
    "Over 25 years of experience in cross-media design, photography and IT. " +
    "Specialized in Python, Java, Docker, CI/CD and secure cloud infrastructure.",
  alternates: {
    canonical: "/en/",
    languages: {
      'de': "https://jozapf.de/",
      'en': "https://jozapf.de/en/",
      "x-default": "https://jozapf.de/",
    },
  },
  openGraph: {
    type: "website",
    siteName: "Jo Zapf",
    url: "https://jozapf.de/en/",
    title: "Jo Zapf – Web Development & Application Development",
    description:
      "Digital solutions from Berlin: Web development, application development, " +
      "cross-media design and secure cloud infrastructure.",
    locale: "en_US",
    alternateLocale: ["de_DE"],
    images: [
      {
        url: "https://assets.jozapf.de/webp/OG_Image_2100x630_jozapf_de.webp",
        secureUrl: "https://assets.jozapf.de/webp/OG_Image_2100x630_jozapf_de.webp",
        width: 2100,
        height: 630,
        alt: "Jo Zapf – Web Development and Application Development from Berlin",
        type: "image/webp",
      },
    ],
  },
  twitter: {
    card: "summary_large_image",
    title: "Jo Zapf – Web Development & Digital Solutions",
    description:
      "Web development, application development and cross-media solutions from Berlin.",
    images: ["https://assets.jozapf.de/webp/OG_Image_2100x630_jozapf_de.webp"],
  },
};

export default function EnLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <>
      {/* Set HTML lang attribute for English version */}
      <script
        dangerouslySetInnerHTML={{
          __html: `document.documentElement.lang = 'en';`,
        }}
      />
      {children}
    </>
  );
}
