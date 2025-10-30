// scripts/diagnose.mjs
import { createServer } from "node:net";
import { execSync } from "node:child_process";
import { existsSync, readFileSync, rmSync } from "node:fs";
import path from "node:path";

const FIX = process.argv.includes("--fix");
const ROOT = process.cwd();

const green = (s) => `\x1b[32m${s}\x1b[0m`;
const yellow = (s) => `\x1b[33m${s}\x1b[0m`;
const red = (s) => `\x1b[31m${s}\x1b[0m`;
const cyan = (s) => `\x1b[36m${s}\x1b[0m`;

function log(kind, msg) {
  const tag = kind === "OK" ? green("OK")
    : kind === "FIXED" ? cyan("FIXED")
    : kind === "WARN" ? yellow("WARN")
    : red("ERR");
  console.log(`${tag}  ${msg}`);
}

async function isPortFree(port) {
  return new Promise((resolve) => {
    const srv = createServer()
      .once("error", (err) => resolve(err.code !== "EADDRINUSE"))
      .once("listening", () => srv.close(() => resolve(true)))
      .listen(port, "0.0.0.0");
  });
}

function fileHasSourceMapHint(p) {
  if (!existsSync(p)) return false;
  const t = readFileSync(p, "utf8");
  return /sourceMappingURL=/.test(t);
}

async function main() {
  // 1) Node-Version
  const [maj, min] = process.versions.node.split(".").map(Number);
  if (maj > 18 || (maj === 18 && min >= 18)) {
    log("OK", `Node ${process.versions.node}`);
  } else {
    log("WARN", `Node ${process.versions.node} (<18.18). Empfohlen: >=18.18`);
  }

  // 2) .next/dev/lock
  const lock = path.join(ROOT, ".next", "dev", "lock");
  if (existsSync(lock)) {
    if (FIX) {
      try { rmSync(lock, { force: true }); log("FIXED", "Lock entfernt: .next/dev/lock"); }
      catch (e) { log("ERR", `Lock entfernen fehlgeschlagen: ${e.message}`); }
    } else {
      log("WARN", "Lock vorhanden: .next/dev/lock (mit --fix entfernen)");
    }
  } else {
    log("OK", "Kein Dev-Lock gefunden");
  }

  // 3) Ports 3000/3002
  for (const p of [3000, 3002]) {
    const free = await isPortFree(p);
    if (free) {
      log("OK", `Port ${p} frei`);
    } else {
      if (FIX) {
        try { execSync(`npx kill-port ${p}`, { stdio: "ignore" }); log("FIXED", `Port ${p} freigemacht (kill-port)`); }
        catch (e) { log("ERR", `Port ${p} kill fehlgeschlagen: ${e.message}`); }
      } else {
        log("WARN", `Port ${p} belegt (mit --fix freigeben)`);
      }
    }
  }

  // 4) Sourcemap-Hinweise (Bootstrap)
  const css = "public/assets/css/bootstrap.css";
  const js  = "public/assets/js/bootstrap.bundle.min.js";
  const cssMap = fileHasSourceMapHint(css);
  const jsMap  = fileHasSourceMapHint(js);
  if (!existsSync(css) || !existsSync(js)) {
    log("WARN", "Bootstrap-Dateien fehlen oder Pfade abweichend (public/assets/... prüfen)");
  } else if (!cssMap && !jsMap) {
    log("OK", "Keine sourceMappingURL-Hinweise in Bootstrap-Dateien");
  } else if (FIX) {
    try {
      execSync("node scripts/remove-sourcemap.mjs", { stdio: "inherit" });
      log("FIXED", "sourceMappingURL-Hinweise entfernt");
    } catch (e) {
      log("ERR", `Strip sourcemaps fehlgeschlagen: ${e.message}`);
    }
  } else {
    log("WARN", "sourceMappingURL vorhanden (mit --fix entfernen)");
  }

  // 5) package.json – dev-Script
  try {
    const pkg = JSON.parse(readFileSync(path.join(ROOT, "package.json"), "utf8"));
    const dev = pkg?.scripts?.dev ?? "";
    if (dev.includes("next dev")) {
      log("OK", `dev-Script erkannt: "${dev}"`);
    } else {
      log("WARN", 'dev-Script ohne "next dev" erkannt (scripts.dev prüfen)');
    }
  } catch (e) {
    log("ERR", `package.json nicht lesbar: ${e.message}`);
  }

  // 6) next.config.ts – allowedDevOrigins
  try {
    const nct = readFileSync(path.join(ROOT, "next.config.ts"), "utf8");
    if (/allowedDevOrigins\s*:/.test(nct)) {
      log("OK", "allowedDevOrigins in next.config.ts gefunden");
    } else {
      log("WARN", "allowedDevOrigins nicht gefunden (Top-Level in next.config.ts empfohlen)");
    }
  } catch {
    log("WARN", "next.config.ts nicht gefunden");
  }

  // 7) summary.json
  const sum = path.join(ROOT, "public", "summary.json");
  if (existsSync(sum)) log("OK", "public/summary.json vorhanden");
  else log("WARN", "public/summary.json fehlt (Generator ausführen)");

  console.log("\n" + cyan("Diagnose abgeschlossen.") + (FIX ? " (Fix-Modus)" : ""));
}

main().catch((e) => {
  log("ERR", e?.stack || e?.message || String(e));
  process.exitCode = 1;
});
