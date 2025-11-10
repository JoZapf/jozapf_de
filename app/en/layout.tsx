// app/en/layout.tsx
import type { Metadata } from "next";

// Metadata for English version
export const metadata: Metadata = {
  title: "Jo Zapf - Web Development, Application Development & Cross-Media Solutions | Berlin",
  description:
    "Jo Zapf offers professional web development, application development and cross-media solutions. Specializing in Python, Java, JavaScript, Docker, CI/CD and secure cloud infrastructure.",
  alternates: {
    canonical: "/en/",
    languages: {
      'de': "https://jozapf.de/",
      'en': "https://jozapf.de/en/",
      "x-default": "https://jozapf.de/",
    },
  },
  openGraph: {
    locale: "en_US",
    alternateLocale: "de_DE",
    url: "https://jozapf.de/en/",
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
