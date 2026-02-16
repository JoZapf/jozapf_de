# Cheatsheet – jozapf.de

Quick Reference für Entwicklung, Build und Deployment.

---

## Dev-Server

```bash
npm run dev                     # Startet auf localhost:3000 (Turbopack)
```

**Cache-Probleme?**
```bash
Remove-Item .\.next -Recurse -Force   # PowerShell: .next löschen
npm run dev                            # Neu starten
```

---

## Build & Test

```bash
npm run build                   # Prebuild (Summary + OG-Images) → Next.js Build
npm run lint                    # Linting
```

---

## Pakete verwalten

```bash
npm install                     # Alle Dependencies installieren
npm i <paket> -D                # Dev-Dependency hinzufügen
npm audit                       # Sicherheitsprüfung
npm audit fix                   # Automatisch fixen
npm update                      # Alle Pakete updaten
```

---

## Git – Basics

```bash
git status                      # Was hat sich geändert?
git add -A                      # ALLES stagen (neu + geändert + gelöscht)
git add <datei>                 # Einzelne Datei stagen
git commit -m "Nachricht"       # Commit
git log --oneline -10           # Letzte 10 Commits
```

**Wichtig:** `git add -A` statt manuelles Auflisten → keine vergessenen Dateien.

---

## Git – Branches

```bash
git branch                      # Aktuellen Branch sehen
git checkout develop            # Zu develop wechseln
git checkout main               # Zu main wechseln
git checkout -b feature/xyz     # Neuen Branch erstellen + wechseln
```

---

## Deploy-Workflow (develop → main → Production)

GitHub Actions deployt automatisch bei Push auf **main**.

### 1. Auf develop committen
```bash
git checkout develop
git add -A
git commit -m "feat: Beschreibung"
```

### 2. Version Bump
```bash
npm version patch -m "v%s – Kurzbeschreibung"   # 2.3.0 → 2.3.1 (Bugfix)
npm version minor -m "v%s – Kurzbeschreibung"   # 2.3.0 → 2.4.0 (Feature)
npm version major -m "v%s – Kurzbeschreibung"   # 2.3.0 → 3.0.0 (Breaking)
```

### 3. Push develop
```bash
git push origin develop
```

### 4. Merge nach main + Deploy
```bash
git checkout main
git pull origin main --rebase
git merge develop
git push origin main --tags
```

### 5. Zurück zu develop
```bash
git checkout develop
```

---

## Versioning (SemVer)

| Typ | Wann | Beispiel |
|---|---|---|
| `patch` | Bugfix, kleiner Tweak | Typo, CSS-Fix |
| `minor` | Neues Feature, neue Seite | Praktikumsseiten |
| `major` | Breaking Change, großer Umbau | Framework-Wechsel |

---

## Troubleshooting

### Push rejected ("remote contains work you don't have")
```bash
git pull origin main --rebase
git push origin main
```

### Neue Dateien nicht im Repo
```bash
git ls-files <pfad>             # Prüfen ob getrackt
git add -A                      # Alles stagen (inkl. neue Dateien!)
```

### TypeScript Build-Fehler in docs/
Nicht Teil der Hauptseite – trotzdem fixen, sonst blockiert `npm run build`.

### Git Working Directory not clean (bei npm version)
```bash
git add -A
git commit -m "WIP"
npm version minor -m "v%s – Beschreibung"
```

### Port belegt
```bash
npx kill-port 3000              # Port freigeben
```

---

## Projektstruktur (Kurzform)

```
app/
├── layout.tsx                  # Root Layout (CSS, Metadata, JSON-LD)
├── page.tsx                    # Startseite DE
├── header-fragment.html        # Header DE
├── footer-fragment.html        # Footer DE
├── home-fragment.html          # Content DE
├── praktikum-fragment.html     # Praktikum Content DE
├── pflichtpraktikum-…/
│   └── page.tsx                # Praktikum Route DE
└── en/
    ├── page.tsx                # Startseite EN
    ├── header-fragment.html
    ├── footer-fragment.html
    ├── home-fragment.html
    ├── internship-fragment.html
    └── pflichtpraktikum-…/
        └── page.tsx            # Praktikum Route EN

public/assets/css/
├── cover.css                   # Haupt-Stylesheet
├── variables.css               # CSS Custom Properties
└── breakpoints.css             # Responsive Breakpoints
```

---

## Scope-Klassen (CSS)

| Klasse | Zweck | Gesetzt in |
|---|---|---|
| `.page-praktikum` | Overrides nur auf Praktikumsseiten | `page.tsx` (DE + EN) |
| `.intro` | Sektion mit Balken-Styling (`li::before`) | Fragment-HTML |
| `.fact-row` | Zwei-Spalten-Grid im Factsheet | Fragment-HTML |
| `.hero-image` | Bild mit abgerundeten Ecken | Fragment-HTML |

---

*Letzte Aktualisierung: 2026-02-16 · v2.3.0*
