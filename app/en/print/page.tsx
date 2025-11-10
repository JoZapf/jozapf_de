// app/en/print/page.tsx
import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Print Version | Jo Zapf - Portfolio",
  description: "Print-friendly version of Jo Zapf's portfolio",
  robots: {
    index: false, // Don't index print versions
    follow: false,
  },
};

export default function PrintEN() {
  return (
    <main className="p-6">
      <h1>Print Version</h1>
      <p>Print-friendly portfolio version (English)</p>
      {/* TODO: Add print-optimized content */}
    </main>
  );
}
