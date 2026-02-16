// app/pflichtpraktikum-anwendungsentwicklung-berlin/page.tsx
import fs from "node:fs";
import path from "node:path";
import type { Metadata } from "next";

export const dynamic = "force-static";

export const metadata: Metadata = {
  title: "Pflichtpraktikum Anwendungsentwicklung Berlin – Jo Zapf",
  description:
    "Pflichtpraktikum als Fachinformatiker für Anwendungsentwicklung. " +
    "Praktische Erfahrung mit Next.js, Docker, CI/CD, Linux-Administration und Cloud-Infrastruktur aus Berlin.",
  alternates: {
    canonical: "/pflichtpraktikum-anwendungsentwicklung-berlin/",
    languages: {
      'de': "https://jozapf.de/pflichtpraktikum-anwendungsentwicklung-berlin/",
      'en': "https://jozapf.de/en/pflichtpraktikum-anwendungsentwicklung-berlin/",
      "x-default": "https://jozapf.de/pflichtpraktikum-anwendungsentwicklung-berlin/",
    },
  },
};

function readFragment(name: string) {
  const candidates = [
    path.join(process.cwd(), "app", "pflichtpraktikum-anwendungsentwicklung-berlin", name),
    path.join(process.cwd(), "app", name),
  ];

  for (const filePath of candidates) {
    if (fs.existsSync(filePath)) {
      return fs.readFileSync(filePath, "utf8");
    }
  }

  throw new Error(`Fragment "${name}" not found in candidates: ${candidates.join(", ")}`);
}

export default function PraktikumPage() {
  const header = readFragment("header-fragment.html");
  const content = readFragment("praktikum-fragment.html");
  const footer = readFragment("footer-fragment.html");

  return (
    <>
      <div dangerouslySetInnerHTML={{ __html: header }} />
      <main id="main-content" dangerouslySetInnerHTML={{ __html: content }} />
      <div dangerouslySetInnerHTML={{ __html: footer }} />
    </>
  );
}
