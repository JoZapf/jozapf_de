import { readFileSync, writeFileSync } from "node:fs";

const strip = (txt) =>
  txt
    // CSS: /*# sourceMappingURL=... */
    .replace(/\/\*#\s*sourceMappingURL=.*?\*\/\s*/gs, "")
    // JS: //# sourceMappingURL=...
    .replace(/^\s*\/\/#\s*sourceMappingURL=.*$/gm, "");

for (const p of [
  "public/assets/css/bootstrap.css",
  "public/assets/js/bootstrap.bundle.min.js",
]) {
  const data = readFileSync(p, "utf8");
  writeFileSync(p, strip(data));
  console.log("stripped sourceMappingURL in", p);
}
