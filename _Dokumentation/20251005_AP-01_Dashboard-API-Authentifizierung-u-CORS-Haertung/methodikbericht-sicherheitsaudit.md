
# Methodikbericht: Vorgehen bei der Suche nach Sicherheitskonflikten
> **Projektkontext:** Contact-Formular, Admin-Dashboard, Logging/Blocklists  
> **Ziel:** Systematische, reproduzierbare und evidenzbasierte Identifikation von Schwachstellen inkl. Validierung  
> **Stand:** 05.10.2025 (Europe/Berlin)

---

## 1. Überlegungen zur grundsätzlichen Vorgehensweise
- **Ziele & Scope klären:** Welche Daten werden verarbeitet (PII, E-Mail, IP), welche Komponenten sind im Spiel (Handler, Validator, Dashboard, API, Logger), welche Trust-Grenzen gibt es (Client ↔ Server, Admin ↔ Public, Webserver ↔ Filesystem)?
- **Sicherheitsziele ableiten (CIA/AAA):**
  - **Confidentiality:** Schutz personenbezogener Daten (E-Mail, IP) und Geheimnisse (.env).
  - **Integrity:** Manipulationsschutz (Blocklist, Logs, Audit-Spuren).
  - **Availability:** Abwehr von Spam/DoS/Ressourcen-Exhaustion.
  - **Authentication/Authorization/Accounting:** Starker Admin-Zugriff, Rechteprüfung, Nachvollziehbarkeit.
- **Threat Modeling-Light:** Missbrauchsfälle/Bedrohungen pro Komponente skizzieren (Anwender, Angreifer, Bot, Admin, Insider).
  - Mapped grob an **STRIDE**: Spoofing (Auth), Tampering (Logs/Blocklisten), Repudiation (fehlende Auditability), Information Disclosure (API/CORS), DoS (Filesystem/Rate-Limits), Elevation of Privilege (Dashboard).
- **Normative Referenzen:** **OWASP ASVS** (App-Security), **OWASP Top 10** (A01–A10), **CWE**-Muster sowie DSGVO-Prinzipien (Datenminimierung, Speicherbegrenzung, Rechenschaft).
- **Risk-Bewertung:** CVSS-ähnliche Heuristik bzw. **Impact × Likelihood**-Matrix zur Priorisierung (kritisch/hoch/mittel/niedrig).

---

## 2. Evaluierung von Methoden
- **Static Application Security Testing (SAST):**
  - Manuelle Code-Inspektion (PHP/JS) auf AuthN/AuthZ, Input-Validierung, Output-Encoding, CSRF, CORS, Secrets, Logging, Dateipfade/Rechte.
  - Statische Checks gegen bekannte Anti-Patterns (Plaintext-Passwortvergleich, `Access-Control-Allow-Origin: *`, direkte Dateizugriffe).
- **Threat Modeling & Data Flow Review:**
  - Datenfluss von Client → Handler → Logger/Blocklist/Dashboard → API.
  - Identifikation von **Trust-Boundaries** (Browser, Cookie, Token, Filesystem, SMTP).
- **Dynamic/Behavioural Review (gedanklich/gezielt simuliert):**
  - Testfälle per `curl`/Browser: CSRF-Szenarien, CORS-Fetch gegen API, Bruteforce-Login (ohne Rate-Limit), Proxy-Header-Manipulation.
- **Compliance-/Privacy-Review:**
  - DSGVO (Art. 5/6, Speicherbegrenzung & Anonymisierung, Datenminimierung in API/Logs).
- **Operations-/Infra-Review:**
  - Webserver-Header/CSP/HSTS, Datei-/Verzeichnisrechte, Position von `logs/` & `data/`, Cron/Rotation, Backup/Egress-Risiko.

**Begründung der Methodenwahl:**  
Kombination aus **Code-zentrierter Analyse** (SAST) + **Bedrohungsbild** (Threat Modeling) + **Privacy/Compliance** + **Betriebsaspekten** führt zu ganzheitlicher Bewertung und priorisierbaren Maßnahmen.

---

## 3. Strukturierung und logische Abläufe
1. **Inventarisierung** der Artefakte (PHP/JS-Dateien, Konfiguration, Secretsfluss).
2. **Datenflussdiagramm (mental/textuell):**
   - Browser (Form) → `contact-php-handler.php` → `ContactFormValidator-v2.php` → `ExtendedLogger.php`/`BlocklistManager.php` → E-Mail / Dashboard / `dashboard-api.php`.
3. **Kontrollpunkte je Boundary:**
   - **Eingabe** (Validator, Captcha, Spam-Score, Honeypot, Zeitheuristik).
   - **AuthN/AuthZ** (Dashboard-Token, Cookie-Flags).
   - **State Changes** (Block-/Whitelist, Logschreiben).
   - **Ausgabe** (Dashboard-HTML-Encoding, API-JSON).
4. **Checklisten-geleitete Prüfung** anhand OWASP-Top-10/ASVS-Kapiteln:
   - A01-Broken Access Control, A02-Cryptographic Failures, A03-Injection (Header/JSON), A05-Security Misconfiguration (CORS/Headers), A07-Identification and Authentication Failures, A08-Software/Data Integrity Failures, A09-Security Logging and Monitoring Failures, A10-Server-Side Request Forgery (nicht relevant hier).
5. **Risiko-Scoring & Priorisierung** (kritisch → sofort, hoch → kurzfristig, mittel → mittelfristig).

---

## 4. Tatsächliche Prüfung (Ausschnitte & Beispiele)
- **CORS/PII:** `dashboard-api.php` mit `Access-Control-Allow-Origin: *` + Ausgabe von E-Mail/IP. Angriffsszenario: Fremdseite liest PII ohne Auth → **kritisch**.
  - **Verifikation:** `curl -i https://host/dashboard-api.php` → Response enthält PII-Felder.
- **CSRF:** `dashboard.php` POST-Aktionen ohne CSRF-Token. Angriffsszenario: Social-Engineering, Tab-Navigation, bösartige Seite löst Admin-POST aus → **hoch**.
  - **Verifikation:** HTML-Output/Forms prüfen; kein CSRF-Hidden-Feld/Server-Check vorhanden.
- **Auth-Handling:** `dashboard-login.php` vergleicht Klartext-Passwort gegen `.env`-Wert; kein Hash, kein Rate-Limit → **hoch**.
  - **Verifikation:** Codepfad zeigt `===`-Vergleich, keine `password_verify()`-Nutzung.
- **Privacy/Retention:** `ExtendedLogger.php` hat Anonymisierungsfunktion, aber keinen Orchestrierer (Cron/Hook) → **mittel/Compliance**.
  - **Verifikation:** Kein Aufruf in den bereitgestellten Dateien.
- **Filesystem-Risiko:** `logs/` und `data/` relativ zu `__DIR__` → potenziell unter Webroot, abhängig vom Deploy → **mittel**.
  - **Verifikation:** Ordnerlage/Server-Konfig nicht ersichtlich; Annahme dokumentiert.
- **Real-IP/Proxy:** Nutzung `REMOTE_ADDR` ohne Proxy-Kontextprüfung → **mittel** (Fehlzuordnung/Rate-Limit-Wirkung).
  - **Verifikation:** Keine robuste `X-Forwarded-For`-Validierung im Code ersichtlich.

---

## 5. Validierung von Ergebnissen
- **Reproduzierbarkeit:** Jeder Befund ist mit **konkreten Triggern** testbar (API-Call, fehlendes CSRF-Feld, Login-Flow ohne Hash).
- **Gegenprobe/Fehlalarme:**
  - **CORS/PII:** Wenn API in Produktion bereits hinter Reverse Proxy/WAF/Auth hängt → Befund in Prod prüfen; Empfehlung bleibt (Defense-in-Depth, PII-Minimierung).
  - **CSRF:** SameSite=Strict reduziert Risiko, aber kein Ersatz. Validierungsmaßnahme: Token einbauen und **Exploit erneut versuchen** → Erwartung: 403.
  - **Passwort-Hash:** Nach Umstellung auf Argon2id + Rate-Limit → Bruteforce-Skript sollte nach N Versuchen geblockt werden.
  - **Anonymisierung:** Cron-Job aktivieren, Logs nach 14 Tagen stichprobenartig prüfen → IP-Teile maskiert.
- **Akzeptanzkriterien (Beispiele):**
  - API liefert **ohne** gültiges Token nur 401 + keine PII.
  - Jede POST-Aktion **erfordert** valides CSRF-Token.
  - Admin-Login **nutzt** `password_verify()` + Lockout/Ratelimit.
  - Logs außerhalb Webroot **oder** serverseitig blockiert; Rechte korrekt.
  - Security-Header/CSP aktiv, via `curl -I` und Browser-DevTools verifizierbar.

---

## 6. Ergänzende, häufig übersehene Aspekte (deine Liste sinnvoll erweitert)
- **Sichere Konfiguration & Header (Site-weit):** CSP, HSTS, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`.
- **Secrets-Management & Rotation:** `.env`-Rechte, kein Check-in, dokumentierte Rotation, Secret-Entropie.
- **Rate-Limiting & Abuse-Prevention:** Für Formular, API und Login (App- und/oder Proxy-Ebene).
- **Auditierbarkeit & Forensik:** Änderungshistorie (Block/Unblock/Whitelist) mit Benutzer-ID (aus Token), Zeit und Grund; separater Audit-Log.
- **Token-Lifecycle:** `jti`/Deny-List, Rolling Expiration, expliziter Logout/Invalidation-Fluss.
- **Supply-Chain/Dependencies:** PHPMailer-Version, Abhängigkeiten prüfen (Composer `--no-dev`, Security Advisories).
- **Build/Deploy/Infra:** `logs/`/`data/` außerhalb Webroot, Backup-Verschlüsselung, Monitoring & Alerting (Mailversand-Fehler, Peak-Spam).
- **DoS-/Leistungsaspekte:** Logrotation, Indexierung, ggf. Umstieg auf SQLite/PostgreSQL für Statistiken/Rate-Limit.
- **Compliance/DPIA:** Datenklassifikation (PII), Speicherfristen, Betroffenenrechte-Prozess (Auskunft/Löschung), Verzeichnis von Verarbeitungstätigkeiten.
- **Teststrategie:** Unit-Tests für Validator-Heuristiken, E2E-Tests (Login/CSRF/Blockflows), Negativtests (bösartige Inputs), Regression-Checks.

---

## 7. Limitierungen & Nächste Schritte
- **Limitierungen:** Kein Einblick in Webserver/Proxy-Konfiguration, reale Verzeichnisstruktur und `.env`-Deployment; dadurch Annahmen dokumentiert.
- **Nächste Schritte (empfohlen):**
  1. API-Auth/CORS-Härtung implementieren und testen.
  2. CSRF-Token-Flow ergänzen und per Exploit-Gegenprobe validieren.
  3. Passwort-Hashing + Rate-Limit einführen, Bruteforce-Tests durchführen.
  4. Anonymisierung per Cron operationalisieren, Audit-Logs prüfen.
  5. Logs/Daten aus Webroot verlagern, Rechte/Headers/CSP absichern.
  6. Unit-/E2E-Tests aufsetzen, CI-Checks (Lint/SAST/Dependency-Scan) aktivieren.

---

## 8. Reproduzierbare Prüfkommandos (Beispiele)
```bash
# API ohne Token (sollte 401 und keine PII liefern)
curl -i https://example.org/dashboard-api.php

# CORS-Header prüfen
curl -i https://example.org/dashboard-api.php | grep -i access-control-allow-origin

# Admin-Form: Fehlt ein CSRF-Feld?
curl -s https://example.org/dashboard.php | grep -i csrf

# Security-Header
curl -I https://example.org/ | egrep -i '(content-security-policy|strict-transport-security|x-content-type-options|referrer-policy)'
```

---

## 9. Fazit
Durch die Kombination aus **SAST**, **Threat Modeling**, **Privacy-/Compliance-Review** und **Operations-Hardening** wurden die zentralen Risiken identifiziert (offene API/PII, fehlender CSRF-Schutz, Klartext-Passwortvergleich, fehlende automatische Anonymisierung, potentiell exponierte Verzeichnisse). Die empfohlenen Maßnahmen sind **konkret, testbar und priorisiert**, um schnelle Wirkung (Sofortmaßnahmen) und nachhaltige Sicherheit (mittelfristige Schritte) zu erreichen.
