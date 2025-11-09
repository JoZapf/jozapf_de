# GitHub Code Scanning Aktivierung - Schritt für Schritt

## ❌ Aktuelles Problem

```
Error: Code scanning is not enabled for this repository. 
Please enable code scanning in the repository settings.
```

## ✅ Lösung: Code Scanning aktivieren

### Option 1: Via GitHub Web UI (Empfohlen)

1. **Öffne dein Repository auf GitHub**
   ```
   https://github.com/JoZapf/jozapf-de
   ```

2. **Navigiere zu Settings**
   ```
   Repository → Settings (Tab oben)
   ```

3. **Öffne Security & Analysis**
   ```
   Linke Sidebar → Code security and analysis
   ```

4. **Aktiviere Code Scanning**
   ```
   Scrolle zu "Code scanning"
   → Klicke auf "Set up" Button
   → Wähle "Advanced"
   → GitHub erkennt automatisch die codeql.yml
   → Bestätige mit "Start commit" → "Commit new file"
   ```

   **WICHTIG:** Wähle **NICHT** "Default" setup, da wir bereits eine eigene `codeql.yml` haben!

### Option 2: Via GitHub CLI

```bash
# Code Scanning aktivieren
gh api \
  --method PUT \
  -H "Accept: application/vnd.github+json" \
  /repos/JoZapf/jozapf-de/code-scanning/default-setup \
  -f state=configured

# Verifizieren
gh api /repos/JoZapf/jozapf-de/code-scanning/default-setup
```

---

## 🔄 Nach der Aktivierung

### 1. Workflow erneut triggern

```bash
# Commit & Push des gefixten Workflows
git add .github/workflows/codeql.yml
git commit -m "fix(ci): correct CodeQL workflow configuration

- Use 'javascript' language for both JS and TS
- Remove duplicate SARIF upload steps
- Remove unnecessary fetch-depth parameter
- Enable security-extended queries

Fixes: #1 (CodeQL Analysis failing)"

git push origin main

# Warte 30 Sekunden, dann:
gh workflow run codeql.yml

# Status prüfen
gh run watch
```

### 2. Erwartetes Ergebnis

Nach ~5 Minuten solltest du sehen:

```
✓ Analyze Code (javascript) - Success
```

**Dann sind sichtbar:**
- ✅ Security Tab → Code scanning alerts
- ✅ Workflow Badge wird grün
- ✅ Automatische Scans bei jedem Push

---

## 🎯 Was wurde gefixt?

### Problem 1: JavaScript + TypeScript Konflikt
```yaml
# ❌ Vorher (Fehler: Doppelte Alerts)
matrix:
  language: [ 'javascript', 'typescript' ]

# ✅ Jetzt (CodeQL erkennt TypeScript automatisch)
matrix:
  language: [ 'javascript' ]
```

**Erklärung:** CodeQL behandelt JavaScript und TypeScript als eine Sprache. Beide im Matrix führt zu duplicate alerts.

### Problem 2: Doppelter SARIF Upload
```yaml
# ❌ Vorher (Fehler: "only one upload allowed")
- name: Perform CodeQL Analysis
  uses: github/codeql-action/analyze@v3
  with:
    upload: true  # Upload 1

- name: Upload SARIF results
  uses: github/codeql-action/upload-sarif@v3  # Upload 2 (Doppelt!)
```

```yaml
# ✅ Jetzt (Nur ein Upload via analyze)
- name: Perform CodeQL Analysis
  uses: github/codeql-action/analyze@v3
  with:
    category: "/language:${{matrix.language}}"
```

**Erklärung:** `analyze` uploaded bereits automatisch. Separater `upload-sarif` Step ist nicht nötig.

### Problem 3: Code Scanning nicht aktiviert
**Lösung:** Manuelle Aktivierung in GitHub Settings (siehe oben)

---

## 📊 Verifizierung

### Nach erfolgreichem Run:

```bash
# Check Workflow Status
gh run list --workflow=codeql.yml --limit 1

# Check Code Scanning Alerts
gh api /repos/JoZapf/jozapf-de/code-scanning/alerts

# Oder in der Web UI:
# Repository → Security → Code scanning
```

**Erwartung:**
- ✅ 0 critical alerts (bei sauberem Code)
- ✅ Evtl. ein paar low-severity Hinweise
- ✅ Badge im README wird grün

---

## 🐛 Troubleshooting

### "Code scanning is still not enabled"

**Lösung:**
```bash
# Prüfe Repository Settings
gh api /repos/JoZapf/jozapf-de | jq '.permissions'

# Falls "admin" fehlt:
# Du brauchst Admin-Rechte im Repository
# Oder: Bitte Repository Owner, Code Scanning zu aktivieren
```

### "Workflow runs but no results"

**Lösung:**
```bash
# Check Workflow Logs
gh run view --log

# Typische Probleme:
# - Build schlägt fehl → Fix build errors
# - Keine .ts/.js Files gefunden → Check file extensions
```

### "Low disk space" Error

**Lösung:**
```yaml
# In codeql.yml hinzufügen (falls nötig)
steps:
  - name: Free disk space
    run: |
      sudo rm -rf /usr/share/dotnet
      sudo rm -rf /opt/ghc
      df -h
```

---

## ✅ Checkliste

Nach der Aktivierung abhaken:

- [ ] Code Scanning in GitHub Settings aktiviert
- [ ] Gefixten `codeql.yml` committed & gepusht
- [ ] Workflow manuell getriggert (`gh workflow run codeql.yml`)
- [ ] Workflow läuft erfolgreich durch (grüner Haken)
- [ ] Security Tab zeigt "Code scanning" Section
- [ ] Badge im README funktioniert
- [ ] Keine Errors mehr in GitHub Actions

---

## 📚 Weiterführende Links

- [GitHub Code Scanning Docs](https://docs.github.com/en/code-security/code-scanning/introduction-to-code-scanning)
- [CodeQL für JavaScript/TypeScript](https://codeql.github.com/docs/codeql-language-guides/codeql-for-javascript/)
- [Security Best Practices](https://docs.github.com/en/code-security/getting-started/securing-your-repository)

---

**Nach Abschluss:** Schließe dieses Dokument und markiere die Aktivierung als erledigt! ✓

---

**Erstellt:** 2024-11-09  
**Status:** Wartend auf Code Scanning Aktivierung in GitHub Settings
