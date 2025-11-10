// app/en/page.tsx
import fs from "node:fs";
import path from "node:path";
import type { Metadata } from "next";

export const dynamic = "force-static";

// SEO Metadata für englische Version
export const metadata: Metadata = {
  title: "Jo Zapf - Web Development, Application Development & Cross-Media Solutions | Berlin",
  description:
    "Jo Zapf offers professional web development, application development and cross-media solutions. Specializing in Python, Java, JavaScript, Docker, CI/CD and secure cloud infrastructure.",
  alternates: {
    canonical: "/en/",
    languages: {
      'de': 'https://jozapf.de/',
      'en': 'https://jozapf.de/en/',
      'x-default': 'https://jozapf.de/'
    }
  },
  openGraph: {
    title: "Jo Zapf - Web Development, Application Development & Cross-Media Solutions",
    description: "Professional digital solutions: Application Development, Web Development, DevOps, Docker, CI/CD from Berlin.",
    url: "https://jozapf.de/en/",
    locale: "en_US",
    alternateLocale: "de_DE",
  },
};

function readFragment(name: string) {
  const filePath = path.join(process.cwd(), "app", "en", name);
  
  if (!fs.existsSync(filePath)) {
    throw new Error(`Fragment "${name}" not found at: ${filePath}`);
  }
  
  return fs.readFileSync(filePath, "utf8");
}

export default function EnglishHome() {
  const header = readFragment("header-fragment.html");
  const main   = readFragment("home-fragment.html");
  const footer = readFragment("footer-fragment.html");

  return (
    <>
      <div dangerouslySetInnerHTML={{ __html: header }} />
      <main id="main-content" dangerouslySetInnerHTML={{ __html: main }} />
      <div dangerouslySetInnerHTML={{ __html: footer }} />
    </>
  );
}
