# Code Scanning Aktivierung - Detaillierte Anleitung

## ❌ Problem: Code Scanning nicht gefunden

Du hast die **Advanced Security** Page geöffnet, aber **Code Scanning** ist dort nicht zu sehen!

---

## ✅ Lösung: Code Security and Analysis Page öffnen

### Navigation (Schritt für Schritt)

```
Repository: https://github.com/JoZapf/jozapf-de
    ↓
Settings Tab (oben)
    ↓
NICHT "Advanced Security" (links) ❌
    ↓
SONDERN: Scrolle runter zu Section "Security" (links)
    ↓
"Code security and analysis" (anklicken) ✓
```

### 🌐 Direkter Link

**Öffne diesen Link direkt:**
```
https://github.com/JoZapf/jozapf-de/settings/security_analysis
```

---

## 📋 Was du dann sehen solltest

Auf der "Code security and analysis" Page solltest du sehen:

```
┌─────────────────────────────────────────────────────────────┐
│ Code security and analysis                                  │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ Private vulnerability reporting                             │
│ [Enable] Allow security researchers to privately...        │
│                                                             │
│ Dependency graph                                            │
│ [Enabled ✓] Understand your dependencies                   │
│                                                             │
│ Dependabot                                                  │
│ Dependabot alerts        [Disable]                         │
│ Dependabot security updates [Disable]                      │
│                                                             │
│ >>> Code scanning <<<  ⚠️ HIER MUSST DU SEIN!             │
│ [Set up ▼]                                                 │
│   - Default                                                 │
│   - Advanced  ← WÄHLE ADVANCED!                            │
│                                                             │
│ Secret scanning                                             │
│ [Enable] (ggf. nur für Pro/Enterprise)                     │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 🎯 Aktivierung (3 Klicks)

### Schritt 1: Set up klicken
```
Code scanning
  └─ [Set up ▼]  ← KLICKEN
```

### Schritt 2: Advanced wählen
```
Dropdown-Menü erscheint:
  ├─ Default (GitHub-managed) ❌ NICHT WÄHLEN
  └─ Advanced (Custom workflow) ✓ ANKLICKEN
```

### Schritt 3: Bestätigen
```
GitHub zeigt:
"We found a CodeQL workflow file in your repository"
  
  [I understand, enable code scanning] ← KLICKEN
```

---

## 🆘 Falls "Code scanning" nicht sichtbar ist

### Mögliche Gründe:

#### 1. Privates Repository + Free Plan
Code Scanning ist nur für:
- ✅ Public Repositories (immer kostenlos)
- ✅ Private Repos mit GitHub Pro/Enterprise

**Lösung:**
```bash
# Repository public machen (temporär für Setup)
# Settings → Danger Zone → Change visibility → Public
```

#### 2. Fehlende Permissions
Du brauchst **Admin-Rechte** im Repository.

**Prüfen:**
```bash
gh api /repos/JoZapf/jozapf-de | jq '.permissions'
# Muss zeigen: "admin": true
```

**Lösung:**
```
Settings → Manage access → Deine Rolle zu "Admin" ändern
```

#### 3. Organization Policy
Falls das Repo zu einer Organization gehört, kann Code Scanning deaktiviert sein.

**Prüfen:**
```bash
gh api /orgs/YOUR_ORG/settings/security_analysis
```

---

## 💡 Alternative: CodeQL Setup via Default Config

Falls Advanced Setup nicht funktioniert, versuche Default:

```
Code scanning → Set up → Default

GitHub erstellt automatisch:
  .github/workflows/codeql-analysis.yml

Du kannst diese Datei dann manuell bearbeiten!
```

---

## 🔄 Plan B: Code Scanning via API aktivieren

```bash
# Aktiviere Code Scanning direkt via API
gh api \
  --method PATCH \
  -H "Accept: application/vnd.github+json" \
  /repos/JoZapf/jozapf-de \
  -f security_and_analysis='{"advanced_security":{"status":"enabled"},"secret_scanning":{"status":"enabled"}}'

# Dann: CodeQL Setup
gh api \
  --method PUT \
  /repos/JoZapf/jozapf-de/code-scanning/default-setup \
  -f state=configured \
  -f languages='["javascript"]'
```

---

## 🎬 Video-Tutorial (falls verfügbar)

GitHub Docs Video:
https://docs.github.com/en/code-security/code-scanning/enabling-code-scanning/configuring-default-setup-for-code-scanning

---

## ✅ Nächste Schritte nach Aktivierung

1. **Warte 2 Minuten** (GitHub aktiviert Code Scanning)

2. **Workflow neu starten:**
   ```bash
   gh workflow run codeql.yml
   gh run watch
   ```

3. **Prüfe Status:**
   ```bash
   gh run list --workflow=codeql.yml --limit 1
   ```

4. **Erwartung:**
   ```
   ✓ Analyze Code (javascript) - Success
   ```

---

## 📸 Screenshot-Checklist

Bitte prüfe, ob du diese Sections siehst:

```
[ ] Private vulnerability reporting
[ ] Dependency graph
[ ] Dependabot
[ ] >>> Code scanning <<< ⚠️ MUSS HIER SEIN!
[ ] Secret scanning (optional)
```

Falls "Code scanning" fehlt:
→ Repository ist privat + Free Plan
→ Lösung: Temporär public machen für Setup

---

## 🆘 Immer noch Probleme?

Schicke mir einen Screenshot von:
```
Settings → Code security and analysis (komplette Seite)
```

Oder zeige mir die Output von:
```bash
gh api /repos/JoZapf/jozapf-de | jq '{
  visibility: .visibility,
  private: .private,
  permissions: .permissions,
  security_and_analysis: .security_and_analysis
}'
```

---

**Wichtig:** Die "Advanced Security" Page (dein Screenshot) ist NICHT die richtige Stelle!  
Du musst zu **"Code security and analysis"** navigieren!

---

**Erstellt:** 2024-11-09  
**Nächster Schritt:** Öffne https://github.com/JoZapf/jozapf-de/settings/security_analysis
