// app/print/page.tsx
import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Druckversion | Jo Zapf - Portfolio",
  description: "Druckfreundliche Version des Portfolios von Jo Zapf",
  robots: {
    index: false, // Druckversionen nicht indexieren
    follow: false,
  },
};

export default function PrintDE() {
  return (
    <main className="p-6">
      <h1>Druckversion</h1>
      <p>Druckoptimierte Portfolio-Version (Deutsch)</p>
      {/* TODO: Druckoptimierten Inhalt hinzufügen */}
    </main>
  );
}
