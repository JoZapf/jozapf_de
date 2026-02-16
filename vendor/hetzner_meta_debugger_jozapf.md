# Python-Meta-Debugger als CGI auf Hetzner Webhosting (Beispiel: jozapf.de)

## Inhaltsverzeichnis

- [Überblick](#überblick)
- [Voraussetzungen](#voraussetzungen)
- [1. SSH-Verbindung herstellen](#1-ssh-verbindung-herstellen)
- [2. Python-Virtualenv im Webspace anlegen](#2-python-virtualenv-im-webspace-anlegen)
- [3. Pakete im Virtualenv installieren](#3-pakete-im-virtualenv-installieren)
- [4. CGI-Testskript für das Virtualenv](#4-cgi-testskript-für-das-virtualenv)
- [5. Meta-Debugger-CGI-Skript anlegen](#5-meta-debugger-cgi-skript-anlegen)
- [6. Aufruf und Nutzung im Browser](#6-aufruf-und-nutzung-im-browser)
- [7. Optional: Aufräumen und Absicherung](#7-optional-aufräumen-und-absicherung)

---

## Überblick

Dieses Dokument beschreibt, wie auf einem **Hetzner Webhosting**-Account ein eigener  
**Python-Meta-Debugger** als **CGI-Skript** eingerichtet wird, der:

- eine URL per HTTP abrufen,
- Open-Graph-Tags (`og:*`), normale `<meta>`-Tags, Bilder und einen Textauszug auslesen,
- und das Ergebnis **direkt im Browser als HTML** ausgeben kann (ohne Dateien zu speichern).

Konkretes Beispiel:

- Benutzer: `jozapf`
- Home-Verzeichnis: `/usr/home/jozapf`
- Hauptdomain: `jozapf.de`
- Webroot der Domain: `/usr/home/jozapf/public_html/jozapf-de`
- Python-Virtualenv: `/usr/home/jozapf/public_html/metaenv`

---

## Voraussetzungen

- Hetzner Webhosting-Paket mit:
  - **SSH-Zugriff**  
  - aktivem Webspace unter `~/public_html`
  - funktionierendem **Python 3** und **CGI**
- Ein funktionierendes, einfaches CGI-Testskript im Webroot (z. B. `test.py`), das bereits gezeigt hat,  
  dass CGI grundsätzlich funktioniert.

Beispielausgabe auf dem Server:

```bash
$ ssh jozapf@www646.your-server.de -p222
Linux www646.your-server.de ...

$ python3 --version
Python 3.11.2
```

---

## 1. SSH-Verbindung herstellen

Von einem lokalen Terminal (z. B. PowerShell unter Windows):

```bash
ssh jozapf@www646.your-server.de -p222
```

Nach erfolgreichem Login:

```bash
cd ~/public_html
pwd
# Erwartet: /usr/home/jozapf/public_html
```

---

## 2. Python-Virtualenv im Webspace anlegen

Das Virtualenv wird **außerhalb** des Domain-Unterordners, aber innerhalb von `public_html` angelegt:

```bash
cd ~/public_html

# Virtualenv "metaenv" anlegen
python3 -m venv metaenv

# Verzeichnisstruktur prüfen
ls -al metaenv
# Erwartet u.a.: bin, lib, include, pyvenv.cfg
```

Das Virtualenv liegt damit unter:

```text
/usr/home/jozapf/public_html/metaenv
```

Der Python-Interpreter im Virtualenv ist:

```text
/usr/home/jozapf/public_html/metaenv/bin/python
```

---

## 3. Pakete im Virtualenv installieren

Im Virtualenv werden die benötigten Pakete installiert:

- `requests`
- `beautifulsoup4`

Aufgrund der Debian-PEP-668-Policy ist es sinnvoll, `python -m pip` direkt aus dem Virtualenv aufzurufen:

```bash
cd ~/public_html/metaenv

# Python-Version im venv prüfen
./bin/python --version
# Erwartet: Python 3.11.2 (oder ähnlich)

# Pakete im venv installieren
./bin/python -m pip install --break-system-packages requests beautifulsoup4
```

Wenn alles korrekt ist, meldet `pip` eine erfolgreiche Installation.

---

## 4. CGI-Testskript für das Virtualenv

Jetzt wird im **Webroot der Domain** (`~/public_html/jozapf-de`) ein Testskript angelegt,  
das sicherstellt, dass:

- der Shebang den richtigen Python aus dem venv verwendet,
- `requests` und `bs4` importiert werden können,
- die CGI-Ausgabe korrekt funktioniert.

### 4.1. Testskript `test_metaenv.py` anlegen

```bash
cd ~/public_html/jozapf-de
nano test_metaenv.py
```

Inhalt:

```python
#!/usr/home/jozapf/public_html/metaenv/bin/python
# -*- coding: utf-8 -*-

print("Content-Type: text/plain; charset=utf-8\n")

import sys
print("Python-Executable:", sys.executable)

import requests
from bs4 import BeautifulSoup

print("requests + bs4: OK")
```

**Wichtig:**

- Keine zusätzlichen Leerzeilen vor `print("Content-Type: ...")`
- Der Shebang muss exakt auf den Python im Virtualenv verweisen.

Datei ausführbar machen:

```bash
chmod 755 test_metaenv.py
```

### 4.2. Test im Browser

Im Browser aufrufen:

```text
https://jozapf.de/test_metaenv.py
```

Erwartete Ausgabe (Beispiel):

```text
Python-Executable: /usr/home/jozapf/public_html/metaenv/bin/python
requests + bs4: OK
```

Wenn diese Ausgabe erscheint, ist:

- das Virtualenv korrekt,
- `requests` und `beautifulsoup4` sind installiert,
- CGI mit dem venv-Python funktioniert.

---

## 5. Meta-Debugger-CGI-Skript anlegen

Nun wird das eigentliche Tool `meta_debug_web.py` im selben Verzeichnis angelegt:

```bash
cd ~/public_html/jozapf-de
nano meta_debug_web.py
```

Inhalt:

```python
#!/usr/home/jozapf/public_html/metaenv/bin/python
# -*- coding: utf-8 -*-

import cgi
import html
import sys
import traceback
from urllib.parse import urljoin

import requests
from bs4 import BeautifulSoup


def fetch_url(url: str, timeout: int = 10):
    headers = {
        "User-Agent": "MetaDebugWeb/1.0 (+https://jozapf.de)"
    }
    resp = requests.get(url, headers=headers, timeout=timeout, allow_redirects=True)
    return resp


def parse_html(content: str, base_url: str):
    soup = BeautifulSoup(content, "html.parser")

    # Title bestimmen
    if soup.title and soup.title.string:
        page_title = soup.title.string.strip()
    else:
        og_title = soup.find("meta", property="og:title")
        if og_title and og_title.get("content"):
            page_title = og_title["content"].strip()
        else:
            page_title = base_url

    # Open Graph Tags
    og_tags = []
    for meta in soup.find_all("meta"):
        prop = meta.get("property")
        content = meta.get("content")
        if prop and prop.startswith("og:") and content:
            og_tags.append((prop, content))

    # Normale Meta-Tags (ohne og:)
    meta_tags = []
    for meta in soup.find_all("meta"):
        name = meta.get("name") or meta.get("property")
        content = meta.get("content")
        if not name or not content:
            continue
        if name.startswith("og:"):
            continue
        meta_tags.append((name, content))

    # Bilder (og:image + img[src])
    image_urls = []
    seen = set()

    for meta in soup.find_all("meta", property="og:image"):
        content = meta.get("content")
        if not content:
            continue
        abs_url = urljoin(base_url, content.strip())
        if abs_url not in seen:
            seen.add(abs_url)
            image_urls.append((abs_url, "og:image"))

    for img in soup.find_all("img"):
        src = img.get("src")
        if not src:
            continue
        abs_url = urljoin(base_url, src.strip())
        if abs_url in seen:
            continue
        seen.add(abs_url)
        alt = img.get("alt") or ""
        image_urls.append((abs_url, alt or "img tag"))
        if len(image_urls) >= 30:
            break

    # Textvorschau
    full_text = soup.get_text(separator=" ", strip=True)
    full_text = " ".join(full_text.split())
    text_preview = full_text[:2000]

    return page_title, og_tags, meta_tags, image_urls, text_preview


def build_html_page(url: str,
                    resp=None,
                    page_title="",
                    og_tags=None,
                    meta_tags=None,
                    image_urls=None,
                    text_preview="",
                    error_message=None):
    og_tags = og_tags or []
    meta_tags = meta_tags or []
    image_urls = image_urls or []

    escaped_url = html.escape(url or "", quote=True)
    escaped_title = html.escape(page_title or "")
    status_code = resp.status_code if resp is not None else "-"
    final_url = resp.url if resp is not None else url
    escaped_final_url = html.escape(final_url or "", quote=True)

    og_rows_html = "".join(
        f"<tr><td>{html.escape(prop)}</td><td>{html.escape(content)}</td></tr>"
        for prop, content in og_tags
    )

    meta_rows_html = "".join(
        f"<tr><td>{html.escape(name)}</td><td>{html.escape(content)}</td></tr>"
        for name, content in meta_tags
    )

    image_blocks_html = "".join(
        f"""
        <figure class="image-item">
            <img src="{html.escape(src, quote=True)}" alt="{html.escape(alt)}">
            <figcaption>{html.escape(alt)}<br><code>{html.escape(src)}</code></figcaption>
        </figure>
        """
        for src, alt in image_urls
    )

    text_preview_html = html.escape(text_preview or "")

    error_block = ""
    if error_message:
        error_block = f"""
        <div class="card error">
            <h2>Fehler</h2>
            <p>{html.escape(error_message)}</p>
        </div>
        """

    # HTTP-Header
    html_page = "Content-Type: text/html; charset=utf-8\n\n"

    html_page += f"""<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Meta Debug Web</title>
    <style>
        body {{
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            margin: 2rem;
            line-height: 1.5;
            background: #f5f5f5;
        }}
        h1, h2, h3 {{
            margin-bottom: 0.5rem;
        }}
        .card {{
            background: #ffffff;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.06);
        }}
        .card.error {{
            border-left: 4px solid #e53935;
        }}
        form {{
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }}
        input[type="text"] {{
            flex: 1;
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            border: 1px solid #ccc;
            font-size: 1rem;
        }}
        button {{
            padding: 0.5rem 1rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            background: #1976d2;
            color: #fff;
            font-size: 1rem;
        }}
        button:hover {{
            background: #1565c0;
        }}
        table {{
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }}
        th, td {{
            border-bottom: 1px solid #ddd;
            padding: 0.5rem 0.75rem;
            vertical-align: top;
        }}
        th {{
            text-align: left;
            background: #fafafa;
            font-weight: 600;
        }}
        code {{
            font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.85em;
        }}
        .images-grid {{
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }}
        .image-item {{
            background: #ffffff;
            border-radius: 6px;
            padding: 0.5rem;
            max-width: 260px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }}
        .image-item img {{
            max-width: 100%;
            height: auto;
            border-radius: 4px;
            display: block;
        }}
        .image-item figcaption {{
            margin-top: 0.25rem;
            font-size: 0.8rem;
            color: #555;
        }}
        pre {{
            white-space: pre-wrap;
            word-wrap: break-word;
            background: #111;
            color: #f5f5f5;
            padding: 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            max-height: 400px;
            overflow-y: auto;
        }}
        .meta-kv {{
            font-size: 0.9rem;
        }}
        .meta-kv dt {{
            font-weight: 600;
        }}
        .meta-kv dd {{
            margin: 0 0 0.3rem 0;
        }}
    </style>
</head>
<body>
    <h1>Meta Debug Web</h1>

    <div class="card">
        <h2>URL analysieren</h2>
        <form method="get" action="">
            <input type="text" name="url" value="{escaped_url}" placeholder="https://example.com">
            <button type="submit">Analysieren</button>
        </form>
        <p style="font-size:0.85rem;color:#666;margin-top:0.5rem;">
            Dieses Tool lädt die angegebene Seite, liest OG-Tags, Meta-Tags, Bilder und einen Textauszug aus und zeigt alles direkt hier an.
            Es werden <strong>keine Dateien gespeichert</strong>.
        </p>
    </div>

    {error_block}
"""

    if resp is not None and not error_message:
        html_page += f"""
    <div class="card">
        <h2>Seiteninformationen</h2>
        <dl class="meta-kv">
            <dt>Title:</dt>
            <dd>{escaped_title}</dd>
            <dt>Ausgangs-URL:</dt>
            <dd><a href="{escaped_url}">{escaped_url}</a></dd>
            <dt>Finale URL (nach Redirects):</dt>
            <dd><a href="{escaped_final_url}">{escaped_final_url}</a></dd>
            <dt>HTTP Status Code:</dt>
            <dd><code>{status_code}</code></dd>
        </dl>
    </div>

    <div class="card">
        <h2>Open Graph Tags</h2>
        <table>
            <thead>
                <tr><th>Property</th><th>Content</th></tr>
            </thead>
            <tbody>
                {og_rows_html or "<tr><td colspan='2'><em>Keine OG-Tags gefunden.</em></td></tr>"}
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Meta Tags</h2>
        <table>
            <thead>
                <tr><th>Name/Property</th><th>Content</th></tr>
            </thead>
            <tbody>
                {meta_rows_html or "<tr><td colspan='2'><em>Keine Meta-Tags gefunden.</em></td></tr>"}
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Bilder (og:image &lt;img&gt;)</h2>
        <div class="images-grid">
            {image_blocks_html or "<p><em>Keine Bilder gefunden.</em></p>"}
        </div>
    </div>

    <div class="card">
        <h2>Textvorschau (erste 2000 Zeichen)</h2>
        <pre>{text_preview_html}</pre>
    </div>
"""

    html_page += """
</body>
</html>
"""
    return html_page


def main():
    form = cgi.FieldStorage()
    url = form.getfirst("url", "").strip()

    # Nur Formular anzeigen, wenn keine URL übergeben wurde
    if not url:
        page = build_html_page(url="")
        sys.stdout.write(page)
        return

    # Seite abrufen
    try:
        resp = fetch_url(url, timeout=10)
    except Exception as e:
        page = build_html_page(
            url=url,
            error_message=f"Fehler beim Abrufen der URL: {e}"
        )
        sys.stdout.write(page)
        return

    # HTML parsen und Report erzeugen
    try:
        page_title, og_tags, meta_tags, image_urls, text_preview = parse_html(
            resp.text, resp.url
        )
        page = build_html_page(
            url=url,
            resp=resp,
            page_title=page_title,
            og_tags=og_tags,
            meta_tags=meta_tags,
            image_urls=image_urls,
            text_preview=text_preview,
        )
        sys.stdout.write(page)
    except Exception:
        # Fallback: Trace als Text ausgeben
        sys.stdout.write("Content-Type: text/plain; charset=utf-8\n\n")
        sys.stdout.write("Fehler bei der HTML-Analyse:\n")
        traceback.print_exc(file=sys.stdout)


if __name__ == "__main__":
    main()
```

Datei ausführbar machen:

```bash
chmod 755 meta_debug_web.py
```

---

## 6. Aufruf und Nutzung im Browser

Im Browser:

```text
https://jozapf.de/meta_debug_web.py
```

Vorgehen:

1. Formular wird angezeigt („URL analysieren“).
2. Zu analysierende URL eintragen (z. B. `https://jozapf.de`).
3. Button **„Analysieren“** klicken.
4. Es erscheint der Report mit:
   - Seiteninformationen (Title, Ausgangs-URL, finale URL, HTTP-Status),
   - Tabelle der Open-Graph-Tags,
   - Tabelle der normalen Meta-Tags,
   - Bildübersicht (`og:image` + `<img src>`),
   - Textvorschau (erste 2000 Zeichen der Seite).

---

## 7. Optional: Aufräumen und Absicherung

**Empfehlungen:**

- Das Testskript `test_metaenv.py` nach Erfolg löschen:

  ```bash
  cd ~/public_html/jozapf-de
  rm test_metaenv.py
  ```

- Zugriff auf `meta_debug_web.py` einschränken, z. B. per `.htaccess`:
  - Basic Auth (Benutzername/Passwort), oder
  - IP-Whitelist, falls nur von festen IPs genutzt.

Beispiel: Basic Auth in `.htaccess` (im selben Verzeichnis wie `meta_debug_web.py`) könnte so aussehen:

```apache
<Files "meta_debug_web.py">
    AuthType Basic
    AuthName "Restricted"
    AuthUserFile /pfad/zu/.htpasswd
    Require valid-user
</Files>
```

Damit bleibt der Meta-Debugger ein internes Tool und ist nicht öffentlich zugänglich.
