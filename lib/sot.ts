/* lib/sot.ts */
import fs from "node:fs";
import path from "node:path";
import child_process from "node:child_process";

export type SiteSummary = {
  project: string;
  version: string;
  lastUpdated: string;  // ISO 8601
  keyPoints: string[];
};

function getPkgVersion(): string {
  try {
    const pkgPath = path.join(process.cwd(), "package.json");
    const pkg = JSON.parse(fs.readFileSync(pkgPath, "utf-8"));
    return pkg.version ?? "0.0.0";
  } catch {
    return "0.0.0";
  }
}

function getGitIsoDate(): string {
  try {
    const iso = child_process
      .execSync('git log -1 --pretty="%cI"')
      .toString()
      .trim()
      .replace(/"/g, "");
    return iso || new Date().toISOString();
  } catch {
    return new Date().toISOString();
  }
}

export async function loadSummary(): Promise<SiteSummary> {
  let keyPoints: string[] = [];
  try {
    const factsPath = path.join(process.cwd(), "data", "facts.json");
    if (fs.existsSync(factsPath)) {
      const data = JSON.parse(fs.readFileSync(factsPath, "utf-8"));
      keyPoints = Array.isArray(data.key_points) ? data.key_points : [];
    }
  } catch { /* noop */ }

  return {
    project: "jozapf.de",
    version: (process.env.GIT_TAG?.replace(/^v/, "") || getPkgVersion()).trim(),
    lastUpdated: (process.env.BUILD_DATE || getGitIsoDate()).trim(),
    keyPoints,
  };
}
