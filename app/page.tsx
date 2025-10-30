// app/page.tsx
import fs from "node:fs";
import path from "node:path";

export const dynamic = "force-static"; // SSG/Export erzwingen

function readFragment(name: string) {
  const candidates = [
    path.join(process.cwd(), "app", "(marketing)", name), // bevorzugter Ort
    path.join(process.cwd(), "app", name),                // Fallback: direkt unter app/
  ];

  for (const p of candidates) {
    if (fs.existsSync(p)) {
      return fs.readFileSync(p, "utf8");
    }
  }

  throw new Error(
    `Fragment "${name}" nicht gefunden. Erwartet unter:\n` +
    `- ${candidates[0]}\n- ${candidates[1]}`
  );
}

export default function Home() {
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
