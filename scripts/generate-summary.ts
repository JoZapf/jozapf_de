// scripts/generate-summary.ts
import fs from "node:fs";
import path from "node:path";

type Summary = {
  project: string;
  version: string;
  lastUpdated: string; // camelCase aus lib/sot
  keyPoints?: string[];
};

async function main() {
  let s: Summary | null = null;

  // Versuche dynamisch, lib/sot zu laden – falls nicht vorhanden: Fallback
  try {
    const mod = await import("../lib/sot"); // dynamischer Import → kein Crash beim Laden
    if (typeof mod.loadSummary === "function") {
      s = await mod.loadSummary();
    }
  } catch {
    // stiller Fallback
  }

  // Fallback-Werte aus CI (oder lokal .env), wenn kein sot verfügbar
  const project = s?.project ?? "jozapf.de";
  const version =
    s?.version ??
    process.env.GIT_TAG ??
    process.env.npm_package_version ??
    "0.0.0-dev";
  const last_updated =
    s?.lastUpdated ?? process.env.BUILD_DATE ?? new Date().toISOString();
  const key_points = s?.keyPoints ?? [];

  const out = { project, version, last_updated, key_points };

  const pubDir = path.join(process.cwd(), "public");
  fs.mkdirSync(pubDir, { recursive: true });
  const file = path.join(pubDir, "summary.json");
  fs.writeFileSync(file, JSON.stringify(out, null, 2) + "\n");
  console.log(
    `[generate-summary] wrote ${file} → ${version} @ ${last_updated} ${s ? "(from lib/sot)" : "(fallback)"}`
  );
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
