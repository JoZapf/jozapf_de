#!/usr/home/jozapf/public_html/metaenv/bin/python
# -*- coding: utf-8 -*-
"""
Meta Debug Web - Erweitert um JSON-LD-Parsing
Version: 2.0.0
Datum: 2025-12-03
"""

import cgi
import html
import json
import sys
import traceback
from urllib.parse import urljoin

import requests
from bs4 import BeautifulSoup


def fetch_url(url: str, timeout: int = 10):
    headers = {
        "User-Agent": "MetaDebugWeb/2.0 (+https://jozapf.de)"
    }
    resp = requests.get(url, headers=headers, timeout=timeout, allow_redirects=True)
    return resp


def extract_json_ld(soup):
    """
    Extrahiert alle JSON-LD-Blöcke aus dem HTML.
    Gibt eine Liste von Dictionaries zurück.
    """
    json_ld_blocks = []
    
    for script in soup.find_all("script", type="application/ld+json"):
        try:
            content = script.string or ""
            if content.strip():
                data = json.loads(content)
                json_ld_blocks.append({
                    "valid": True,
                    "data": data
                })
        except json.JSONDecodeError as e:
            # Ungültiges JSON - trotzdem anzeigen
            raw_content = script.string or ""
            json_ld_blocks.append({
                "valid": False,
                "error": str(e),
                "raw": raw_content[:1000]  # Maximal 1000 Zeichen
            })
    
    return json_ld_blocks


def parse_html(content: str, base_url: str):
    soup = BeautifulSoup(content, "html.parser")

    # Title
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

    # JSON-LD extrahieren
    json_ld_blocks = extract_json_ld(soup)

    return page_title, og_tags, meta_tags, image_urls, text_preview, json_ld_blocks


def format_json_ld_html(json_ld_blocks):
    """
    Formatiert JSON-LD-Blöcke als HTML für die Ausgabe.
    """
    if not json_ld_blocks:
        return "<p><em>Keine JSON-LD-Blöcke gefunden.</em></p>"
    
    html_parts = []
    
    for i, block in enumerate(json_ld_blocks, 1):
        if block.get("valid"):
            data = block["data"]
            # @type extrahieren für bessere Übersicht
            schema_type = data.get("@type", "Unknown")
            if isinstance(schema_type, list):
                schema_type = ", ".join(schema_type)
            
            formatted_json = json.dumps(data, indent=2, ensure_ascii=False)
            escaped_json = html.escape(formatted_json)
            
            html_parts.append(f"""
            <div class="json-ld-block">
                <h4>Schema #{i}: <code>@type: {html.escape(schema_type)}</code> ✓</h4>
                <pre>{escaped_json}</pre>
            </div>
            """)
        else:
            error = block.get("error", "Unknown error")
            raw = block.get("raw", "")
            escaped_raw = html.escape(raw)
            
            html_parts.append(f"""
            <div class="json-ld-block json-ld-error">
                <h4>Schema #{i}: <span class="error-badge">✗ Ungültiges JSON</span></h4>
                <p><strong>Fehler:</strong> {html.escape(error)}</p>
                <pre>{escaped_raw}</pre>
            </div>
            """)
    
    return "".join(html_parts)


def build_html_page(url: str,
                    resp=None,
                    page_title="",
                    og_tags=None,
                    meta_tags=None,
                    image_urls=None,
                    text_preview="",
                    json_ld_blocks=None,
                    error_message=None):
    og_tags = og_tags or []
    meta_tags = meta_tags or []
    image_urls = image_urls or []
    json_ld_blocks = json_ld_blocks or []

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
    json_ld_html = format_json_ld_html(json_ld_blocks)

    error_block = ""
    if error_message:
        error_block = f"""
        <div class="card error">
            <h2>Fehler</h2>
            <p>{html.escape(error_message)}</p>
        </div>
        """

    # WICHTIG: HTTP-Header
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
            background: #345f4f;
        }}
        h1, h2, h3, h4 {{
            margin-bottom: 0.5rem;
        }}
        .card {{
            background: #c3edd2;
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
            background: rgba(0,0,0,0.05);
            padding: 0.1em 0.3em;
            border-radius: 3px;
        }}
        .images-grid {{
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }}
        .image-item {{
            background: #e5ebe9;
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
        /* JSON-LD Styling */
        .json-ld-block {{
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #ccc;
        }}
        .json-ld-block:last-child {{
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }}
        .json-ld-block h4 {{
            margin-top: 0;
            color: #1565c0;
        }}
        .json-ld-block.json-ld-error h4 {{
            color: #c62828;
        }}
        .error-badge {{
            background: #ffcdd2;
            color: #c62828;
            padding: 0.2em 0.5em;
            border-radius: 4px;
            font-size: 0.9em;
        }}
        .json-ld-block pre {{
            max-height: 300px;
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
            Dieses Tool lädt die angegebene Seite, liest OG-Tags, Meta-Tags, <strong>JSON-LD (Structured Data)</strong>, Bilder und einen Textauszug aus.
            Es werden <strong>keine Dateien gespeichert</strong>.
        </p>
    </div>

    {error_block}
"""

    if resp is not None and not error_message:
        # Anzahl der JSON-LD-Blöcke für Übersicht
        json_ld_count = len(json_ld_blocks)
        json_ld_valid = sum(1 for b in json_ld_blocks if b.get("valid"))
        json_ld_invalid = json_ld_count - json_ld_valid
        
        json_ld_summary = f"{json_ld_count} Block(s)"
        if json_ld_invalid > 0:
            json_ld_summary += f" ({json_ld_invalid} ungültig)"

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
            <dt>JSON-LD Schemas:</dt>
            <dd>{json_ld_summary}</dd>
        </dl>
    </div>

    <div class="card">
        <h2>Bilder (og:image &amp; &lt;img&gt;)</h2>
        <div class="images-grid">
            {image_blocks_html or "<p><em>Keine Bilder gefunden.</em></p>"}
        </div>
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
        <h2>Textvorschau (erste 2000 Zeichen)</h2>
        <pre>{text_preview_html}</pre>
    </div>

    <div class="card">
        <h2>Structured Data (JSON-LD)</h2>
        {json_ld_html}
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

    # Nur Formular, wenn keine URL übergeben wurde
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

    # HTML parsen
    try:
        page_title, og_tags, meta_tags, image_urls, text_preview, json_ld_blocks = parse_html(
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
            json_ld_blocks=json_ld_blocks,
        )
        sys.stdout.write(page)
    except Exception as e:
        # Fallback-Ausgabe mit Trace, falls im Parsing was kracht
        sys.stdout.write("Content-Type: text/plain; charset=utf-8\n\n")
        sys.stdout.write("Fehler bei der HTML-Analyse:\n")
        traceback.print_exc(file=sys.stdout)


if __name__ == "__main__":
    main()
