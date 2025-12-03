// app/en/page.tsx
import fs from "node:fs";
import path from "node:path";

export const dynamic = "force-static";

// Metadata ist in app/en/layout.tsx definiert (keine Redundanz)

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
