# Kontaktformular – Sicherheitsmechanismen: Funktionalität & Implementierung

> Version: 1.0 • Datei: `contact-php-handler.hardened.php` • Stand: 29.09.2025

## Inhaltsverzeichnis
1. **Problemdefinition**
2. **Architekturüberblick**
3. **Eingabeverarbeitung (Normalisierung & Validierung)**
4. **Strenge Code-Heuristik (Erkennung von Code-/Markup-Inhalten)**
5. **Mail-Sicherheit (Header, MIME, Body)**
6. **Ausgabe- und Anzeige-Sicherheit (Escaping & UI)**
7. **Fehlerbehandlung & JSON-Antwortschema**
8. **Protokollierung & Forensik**
9. **Konfiguration & Deployment-Hinweise**
10. **Testszenarien & Qualitätssicherung**
11. **Schnell-Checkliste (Go‑Live)**
12. **Anpassung & Fehlertoleranz**
13. **FAQ & typische Fallstricke**

---

## 1) Problemdefinition
Ziel ist es, **aktive Inhalte** (HTML/JS/PHP, SQL-Fragmente, Markdown-Fences, Templating-Marker) aus Kontaktformularen **nicht zur Ausführung kommen zu lassen**, sondern **sicher zu verarbeiten** und – falls gewünscht – **abzuweisen**. Primärschutz erfolgt **serverseitig**:
- **Whitelist-Validierung** für Zeilenfelder (`firstName`, `lastName`, `subject`, `phone`) mit Längenlimits.
- **Strenge Code-Heuristik** auf dem Nachrichtenfeld, die typische Code-/Markup-Muster erkennt und blockiert.
- **Sichere Mail-Erzeugung** inkl. Header-Härtung und sicherer Darstellung (HTML-escaping) im Admin-Body.
- **Konsistente JSON-Fehlerantworten** (HTTP 422 für Validierungsfehler), damit das Frontend korrekt reagieren kann.

> **Wichtig:** Clientseitige Prüfungen sind Komfort, jedoch nicht sicherheitswirksam. Maßgeblich ist die Serverseite.

---

## 2) Architekturüberblick
- **Entry-Point:** `contact-php-handler.hardened.php` (JSON-API).  
- **Vorbereitung:** `declare(strict_types=1)`, erzwungener JSON-Response-Header.  
- **Konfig:** `.env`-Loader (SMTP, Absender, Zieladressen, Betreff-Präfix, Dev/Prod-Modi).  
- **Mailer:** PHPMailer via SMTP (z. B. Hetzner) mit optionaler Bestätigungsmail an Nutzer.  
- **Logs:** `assets/php/logs/` (Debug-Log und optionale EML-Mitschnitte).  
- **Sicherheitsanker:**
  1. Normalisierung/Trim
  2. Whitelist-Regex je Feld
  3. Strenge `looks_like_code()` Heuristik auf Nachricht
  4. Header-Injection-Schutz (keine ungeprüften CR/LF in Headern)
  5. HTML-Escaping bei jeder Anzeige (`e()`)

---

## 3) Eingabeverarbeitung (Normalisierung & Validierung)

### 3.1 Normalisierung
- `trim()` auf alle Zeilenfelder
- Optionale Unicode-Normalisierung (NFC) möglich
- **Max-Längen** vor Validierung setzen (Hard limits)

### 3.2 Whitelist-Regex
**Zweck:** Nur erwartete Zeichen erlauben, statt „alles außer X“ zu verbieten.

- **Name/Betreff (max 120 Zeichen):**
```regex
/^[\p{L}\p{N}\s\.,!?:;@()\+\-'"&\/_]{1,120}$/u
```
- **Telefon (max 40 Zeichen):**
```regex
/^[\p{N}\s\+\-()\/]{0,40}$/u
```
- **E-Mail:** `FILTER_VALIDATE_EMAIL` (PHP) nach `trim()`.

**Fehlerbild:** Bei Verstoß -> **HTTP 422** + JSON-Körper mit Feldmarkierung:
```json
{
  "ok": false,
  "message": "Nachname enthält unzulässige Zeichen.",
  "fields": { "lastName": false }
}
```

---

## 4) Strenge Code-Heuristik (Erkennung von Code-/Markup-Inhalten)

### 4.1 Ziel
Im Nachrichtenfeld **typische Code-/Markup-Indikatoren** erkennen und **abbrechen** (HTTP 422), bevor Inhalte gespeichert/versendet/angezeigt werden.

### 4.2 Kernfunktion (Ausschnitt)
```php
function looks_like_code(string $t): bool {
    $t = trim($t);
    if ($t === '') return false;

    $patterns = [
        '/<\/?[a-z][\w:\.-]*[^>]*>/i',  // HTML/XML-Tags
        '/<\s*script\b/i',
        '/<\s*style\b/i',
        '/<\?php/i',
        '/`{3,}/',                      // Markdown-Fences
        '/\{\{|\}\}/',                  // Templating
        '/\$\(.*\)/s',                  // jQuery
        '/\b(document|window|eval|Function)\b\s*\(/i',
        '/\bon\w+\s*=\s*["\']/i',       // onclick=, onload=, ...
        '/\bjavascript:\s*/i',
        '/\bdata:\s*text\/html/i',
        '/\b(function|class|=>)\b/i',
        '/\b(SELECT|INSERT|UPDATE|DELETE|DROP|UNION)\b\s/i',
    ];
    foreach ($patterns as $rx) {
        if (preg_match($rx, $t)) return true;
    }

    $angles    = substr_count($t, '<') + substr_count($t, '>');
    $braces    = substr_count($t, '{') + substr_count($t, '}');
    $backticks = substr_count($t, '`');
    $lines     = preg_split('/\r?\n/', $t);
    $longLine  = $lines ? max(array_map('strlen', $lines)) : 0;

    if ($angles >= 2 || $braces >= 4 || $backticks >= 3 || $longLine > 300) {
        return true;
    }
    return false;
}
```
**Entscheidung:** Bei `true` -> **422** mit Meldung „Die Nachricht enthält Code-/Markup-Fragmente…“.  
**Hinweis:** Die Heuristik ist **bewusst restriktiv** und kann **False Positives** erzeugen, wenn Nutzer legitime Texte mit Codebegriffen schicken. Feinabstimmung siehe Kapitel 12.

---

## 5) Mail-Sicherheit (Header, MIME, Body)

### 5.1 Header-Injection-Schutz
- Keine ungeprüften Nutzerdaten in `Subject`, `From`, `Reply-To` ohne CR/LF-Prüfung.
- `preg_match("/[\r\n]/", $value)` -> Abbruch bei Fund.

### 5.2 MIME-Strategie
- **Empfehlung maximal sicher:** Nur `text/plain` (kein HTML-Part).  
- **Aktuelle Implementierung:** Admin-Mail als HTML **mit konsequentem Escaping**:
  - Werte via `e()` (HTML-escape) in `<table>`-Darstellung.
  - Nachricht im `<div style="white-space:pre-wrap">` + `nl2br(e($message))`.

### 5.3 Absender-/Empfänger-Setup
- Absenderdomain mit **SPF/DKIM/DMARC** korrekt konfigurieren (Mail-Provider/Hetzner).  
- Envelope-From, `setFrom`, `addAddress`, `addReplyTo` bewusst und statisch wählen; Nutzerdaten **nicht** als „technische Absender“.

---

## 6) Ausgabe- und Anzeige-Sicherheit (Escaping & UI)
- **Admin-UI/Logs:** Niemals ungefilterte Nutzerdaten rendern.  
- **Escaping:** `e()` nutzt `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.  
- **CSP** (Admin-Oberflächen): `default-src 'self'; script-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'`.

---

## 7) Fehlerbehandlung & JSON-Antwortschema
- **Validierungsfehler:** HTTP **422** + `{ ok:false, message, fields }`.  
- **SMTP/Serverfehler:** HTTP **500** + `{ ok:false, message }` (Logeintrag!).  
- **Erfolg:** `{ ok:true, sent: true|false, userConfirm: 'sent'|'disabled'|'not_sent_or_failed' }`.

**Vorteil:** Frontend kann Felder markieren, Nutzerhinweise sauber anzeigen und Retry-Logik implementieren.

---

## 8) Protokollierung & Forensik
- **Debug-Log:** `assets/php/logs/debug.log` (Zeitstempel, Fehlermeldungen, Versandstatus).  
- **Optional EML-Mitschnitt:** Rohmails im Ordner `eml/` für Reproduktion.  
- **Datenschutz:** Logs enthalten nur notwendige Metadaten; Aufbewahrungsfristen definieren und regelmäßig rotieren/löschen.

---

## 9) Konfiguration & Deployment-Hinweise
- `.env` mit SMTP-Credentials, Absender, Empfängern, Betreff-Präfix, Dev/Prod-Schalter.  
- **Dateirechte/Ordner:** `logs/` und `eml/` müssen schreibbar sein.  
- **PHP-Version:** ≥ 8.1 empfohlen (strict types, Unicode).  
- **Reverse Proxy/HTTPS:** TLS erzwingen; Rate-Limit (z. B. via Proxy, WAF, Firewall).  
- **Captcha/Turnstile optional:** Ergänzend gegen Spam-Bots.

---

## 10) Testszenarien & Qualitätssicherung

### 10.1 Positivtests (erwartet „OK“)
- Name „Marta Müller“, Betreff „Anfrage“, Nachricht 300 Zeichen Klartext.  
- Telefonnummer „+49 (30) 123456“.
- E-Mail RFC-konform.

### 10.2 Negativtests (erwartet **422**)
- Nachricht enthält `<script>alert(1)</script>`  
- Nachricht enthält ```` ```python\nprint(1)\n``` ````  
- Nachricht enthält `<?php echo 1; ?>`  
- Nachricht mit 1.000 Zeichen ohne Zeilenumbruch (Heuristik: `longLine > 300`)  
- Name mit nicht erlaubten Symbolen (z. B. „Marta <3“).

### 10.3 Header-Injection
- `subject="Hallo\r\nBCC: attacker@ex.tld"` → **422/Abbruch**.

### 10.4 Mail-Flow
- SMTP erreichbar, Auth korrekt → **OK**. Bei Ausfall → **500** + Logeintrag.

---

## 11) Schnell-Checkliste (Go‑Live)
- [ ] `.env` korrekt (SMTP, Absender, Empfänger, Präfix).  
- [ ] Logs schreibbar, Pfade vorhanden.  
- [ ] CSP aktiv im Admin-Bereich.  
- [ ] Frontend verarbeitet 422/500 sauber.  
- [ ] Testfälle aus 10.2 schlagen an.  
- [ ] Datenschutz-Hinweise (Privacy Policy) verlinkt und dokumentiert.

---

## 12) Anpassung & Fehlertoleranz
- **Zu streng?** Entferne einzelne Regex-Pattern in `looks_like_code()` (z. B. SQL-Keywords) oder erhöhe Schwellwerte (`braces`, `longLine`).  
- **Zu lax?** Ergänze weitere Muster (z. B. `onerror=`, `import`, `require`, `<iframe`, `<object>`, `base64,`).  
- **Internationalisierung:** Erweitere Whitelist um zusätzliche Satzzeichen/Sprachen, halte aber Längenlimits konsequent bei.

---

## 13) FAQ & typische Fallstricke

**F: Warum werden legitime Nachrichten abgelehnt?**  
A: Die Heuristik ist bewusst restriktiv. Wenn Nutzer über Code sprechen (z. B. „Bitte fügen Sie `<div>` ein“), kann das anschlagen. Lösung: Nutzerhinweis oder spezifische Lockerung der Patterns.

**F: Reicht clientseitige Validierung?**  
A: Nein. Client ist manipulierbar. Serverseitige Validierung ist bindend.

**F: Muss ich HTML-Mails vermeiden?**  
A: Sicherer ist `text/plain`. Wenn HTML genutzt wird, dann nur mit **konsequentem Escaping** aller Nutzereingaben und ohne Inline-Skripte.

**F: Sind Logs ein Risiko?**  
A: Nur, wenn sie später unsicher angezeigt werden. Beim Rendern auch dort escapen oder rein textuell speichern/analysieren.

---

### Kurzfazit
Mit **Whitelist-Validierung**, **strenger Code-Heuristik**, **Header-Härtung**, **konsequentem Escaping** und **sauberem Fehler-/Log-Handling** wird verhindert, dass „Code“ interpretiert oder ausgeführt wird. Feintuning bleibt projektspezifisch und ist über klar abgrenzbare Stellen (Regex/Heuristik) gezielt möglich.
