#!/usr/home/jozapf/public_html/metaenv/bin/python
# -*- coding: utf-8 -*-
"""
Meta Debug Web - Enhanced with JSON-LD Parsing
Version: 3.2.0
Date: 2025-12-10

Dark mode UI inspired by Dynamic OG Generator project.
Images limited to: og:image, favicon, and structured data images.
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
        "User-Agent": "MetaDebugWeb/3.2 (+https://jozapf.de)"
    }
    resp = requests.get(url, headers=headers, timeout=timeout, allow_redirects=True)
    
    # Force UTF-8 encoding to fix character issues
    if resp.encoding is None or resp.encoding.lower() == 'iso-8859-1':
        resp.encoding = 'utf-8'
    
    return resp


def extract_json_ld(soup):
    """
    Extracts all JSON-LD blocks from HTML.
    Returns a list of dictionaries.
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
            raw_content = script.string or ""
            json_ld_blocks.append({
                "valid": False,
                "error": str(e),
                "raw": raw_content[:1000]
            })
    
    return json_ld_blocks


def extract_images_from_json_ld(json_ld_blocks, base_url):
    """
    Extracts image URLs from JSON-LD structured data.
    Looks for common image properties like 'image', 'logo', 'photo', 'thumbnail'.
    """
    images = []
    seen = set()
    
    def find_images(obj, schema_type="", path=""):
        """Recursively find image properties in JSON-LD data."""
        if isinstance(obj, dict):
            current_type = obj.get("@type", schema_type)
            if isinstance(current_type, list):
                current_type = ", ".join(current_type)
            
            for key, value in obj.items():
                # Check for image-related keys
                if key.lower() in ("image", "logo", "photo", "thumbnail", "thumbnailurl", "contenturl"):
                    if isinstance(value, str) and value.strip():
                        abs_url = urljoin(base_url, value.strip())
                        if abs_url not in seen:
                            seen.add(abs_url)
                            label = f"{current_type}.{key}" if current_type else key
                            images.append((abs_url, label))
                    elif isinstance(value, dict):
                        # Handle ImageObject
                        img_url = value.get("url") or value.get("contentUrl")
                        if img_url and isinstance(img_url, str):
                            abs_url = urljoin(base_url, img_url.strip())
                            if abs_url not in seen:
                                seen.add(abs_url)
                                label = f"{current_type}.{key}" if current_type else key
                                images.append((abs_url, label))
                    elif isinstance(value, list):
                        for item in value:
                            if isinstance(item, str) and item.strip():
                                abs_url = urljoin(base_url, item.strip())
                                if abs_url not in seen:
                                    seen.add(abs_url)
                                    label = f"{current_type}.{key}" if current_type else key
                                    images.append((abs_url, label))
                            elif isinstance(item, dict):
                                img_url = item.get("url") or item.get("contentUrl")
                                if img_url and isinstance(img_url, str):
                                    abs_url = urljoin(base_url, img_url.strip())
                                    if abs_url not in seen:
                                        seen.add(abs_url)
                                        label = f"{current_type}.{key}" if current_type else key
                                        images.append((abs_url, label))
                else:
                    # Recurse into nested objects
                    find_images(value, current_type, f"{path}.{key}" if path else key)
        elif isinstance(obj, list):
            for item in obj:
                find_images(item, schema_type, path)
    
    for block in json_ld_blocks:
        if block.get("valid"):
            find_images(block["data"])
    
    return images, seen


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

    # Regular Meta Tags (excluding og:)
    meta_tags = []
    for meta in soup.find_all("meta"):
        name = meta.get("name") or meta.get("property")
        content = meta.get("content")
        if not name or not content:
            continue
        if name.startswith("og:"):
            continue
        meta_tags.append((name, content))

    # Extract JSON-LD first (needed for images)
    json_ld_blocks = extract_json_ld(soup)

    # ===== IMAGES: Only meta images, favicon, and structured data =====
    image_urls = []
    seen = set()

    # 1. OG Images
    for meta in soup.find_all("meta", property="og:image"):
        content = meta.get("content")
        if not content:
            continue
        abs_url = urljoin(base_url, content.strip())
        if abs_url not in seen:
            seen.add(abs_url)
            image_urls.append((abs_url, "og:image"))

    # 2. Twitter Image
    twitter_img = soup.find("meta", attrs={"name": "twitter:image"})
    if twitter_img and twitter_img.get("content"):
        abs_url = urljoin(base_url, twitter_img["content"].strip())
        if abs_url not in seen:
            seen.add(abs_url)
            image_urls.append((abs_url, "twitter:image"))

    # 3. Favicon (multiple possible formats)
    favicon_rels = ["icon", "shortcut icon", "apple-touch-icon", "apple-touch-icon-precomposed"]
    for link in soup.find_all("link"):
        rel = link.get("rel", [])
        if isinstance(rel, list):
            rel = " ".join(rel).lower()
        else:
            rel = rel.lower()
        
        href = link.get("href")
        if not href:
            continue
            
        for fav_rel in favicon_rels:
            if fav_rel in rel:
                abs_url = urljoin(base_url, href.strip())
                if abs_url not in seen:
                    seen.add(abs_url)
                    # Determine label based on type
                    if "apple" in rel:
                        label = "apple-touch-icon"
                    else:
                        label = "favicon"
                    image_urls.append((abs_url, label))
                break

    # 4. Images from JSON-LD Structured Data
    json_ld_images, json_ld_seen = extract_images_from_json_ld(json_ld_blocks, base_url)
    for img_url, label in json_ld_images:
        if img_url not in seen:
            seen.add(img_url)
            image_urls.append((img_url, f"JSON-LD: {label}"))

    # Text preview
    full_text = soup.get_text(separator=" ", strip=True)
    full_text = " ".join(full_text.split())
    text_preview = full_text[:2000]

    return page_title, og_tags, meta_tags, image_urls, text_preview, json_ld_blocks


def format_json_ld_html(json_ld_blocks):
    """
    Formats JSON-LD blocks as HTML for output.
    """
    if not json_ld_blocks:
        return "<p class='empty-state'>No JSON-LD blocks found.</p>"
    
    html_parts = []
    
    for i, block in enumerate(json_ld_blocks, 1):
        if block.get("valid"):
            data = block["data"]
            schema_type = data.get("@type", "Unknown")
            if isinstance(schema_type, list):
                schema_type = ", ".join(schema_type)
            
            formatted_json = json.dumps(data, indent=2, ensure_ascii=False)
            escaped_json = html.escape(formatted_json)
            
            html_parts.append(f"""
            <div class="json-ld-block">
                <h4>Schema #{i}: <code>@type: {html.escape(schema_type)}</code> <span class="valid-badge">Valid</span></h4>
                <pre>{escaped_json}</pre>
            </div>
            """)
        else:
            error = block.get("error", "Unknown error")
            raw = block.get("raw", "")
            escaped_raw = html.escape(raw)
            
            html_parts.append(f"""
            <div class="json-ld-block json-ld-error">
                <h4>Schema #{i}: <span class="error-badge">Invalid JSON</span></h4>
                <p><strong>Error:</strong> {html.escape(error)}</p>
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
        f"<tr><td class='prop-cell'>{html.escape(prop)}</td><td class='content-cell'>{html.escape(content)}</td></tr>"
        for prop, content in og_tags
    )

    meta_rows_html = "".join(
        f"<tr><td class='prop-cell'>{html.escape(name)}</td><td class='content-cell'>{html.escape(content)}</td></tr>"
        for name, content in meta_tags
    )

    image_blocks_html = "".join(
        f"""
        <figure class="image-item">
            <img src="{html.escape(src, quote=True)}" alt="{html.escape(alt)}" loading="lazy">
            <figcaption>
                <span class="img-label">{html.escape(alt)}</span>
                <code>{html.escape(src)}</code>
            </figcaption>
        </figure>
        """
        for src, alt in image_urls
    )

    text_preview_html = html.escape(text_preview or "")
    json_ld_html = format_json_ld_html(json_ld_blocks)

    error_block = ""
    if error_message:
        error_block = f"""
        <div class="card error-card">
            <h2>Error</h2>
            <p>{html.escape(error_message)}</p>
        </div>
        """

    # HTTP Header
    html_page = "Content-Type: text/html; charset=utf-8\n\n"

    html_page += f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Jo Zapf Toolbox - Meta Debug Web</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {{
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --accent: #3b82f6;
            --accent-secondary: #8b5cf6;
            --border: #334155;
            --code-bg: #0f172a;
            --success: #10b981;
            --error: #ef4444;
        }}

        * {{
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }}

        html {{
            font-size: 16px;
            scroll-behavior: smooth;
        }}

        body {{
            font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            padding: 2rem;
        }}

        .container {{
            max-width: 1100px;
            margin: 0 auto;
        }}

        h1 {{
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }}

        .subtitle {{
            color: var(--text-secondary);
            font-size: 1rem;
            margin-bottom: 2rem;
        }}

        h2 {{
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }}

        h4 {{
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: var(--text-secondary);
        }}

        .card {{
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }}

        .error-card {{
            border-left: 4px solid var(--error);
        }}

        .error-card h2 {{
            color: var(--error);
        }}

        /* Form */
        form {{
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
        }}

        input[type="text"] {{
            flex: 1;
            min-width: 280px;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 1rem;
            font-family: inherit;
        }}

        input[type="text"]::placeholder {{
            color: var(--text-secondary);
        }}

        input[type="text"]:focus {{
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }}

        button {{
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            background: var(--accent);
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            font-family: inherit;
            transition: background 0.2s, transform 0.1s;
        }}

        button:hover {{
            background: #2563eb;
        }}

        button:active {{
            transform: scale(0.98);
        }}

        .form-hint {{
            width: 100%;
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }}

        /* Tables */
        table {{
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }}

        th, td {{
            border-bottom: 1px solid var(--border);
            padding: 0.75rem 1rem;
            vertical-align: top;
            text-align: left;
        }}

        th {{
            background: var(--bg-primary);
            font-weight: 600;
            color: var(--text-primary);
        }}

        td {{
            color: var(--text-secondary);
        }}

        .prop-cell {{
            width: 200px;
            color: var(--accent);
            font-weight: 500;
        }}

        .content-cell {{
            word-break: break-word;
        }}

        /* Code */
        code {{
            font-family: 'JetBrains Mono', 'Fira Code', Consolas, monospace;
            font-size: 0.85em;
            background: var(--code-bg);
            color: #e879f9;
            padding: 0.15em 0.4em;
            border-radius: 4px;
        }}

        pre {{
            white-space: pre-wrap;
            word-wrap: break-word;
            background: var(--bg-primary);
            color: var(--text-secondary);
            padding: 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid var(--border);
            font-family: 'JetBrains Mono', 'Fira Code', Consolas, monospace;
        }}

        /* Meta Key-Value */
        .meta-kv {{
            font-size: 0.95rem;
        }}

        .meta-kv dt {{
            font-weight: 600;
            color: var(--text-secondary);
            margin-top: 0.75rem;
        }}

        .meta-kv dt:first-child {{
            margin-top: 0;
        }}

        .meta-kv dd {{
            margin: 0.25rem 0 0 0;
            color: var(--text-primary);
        }}

        .meta-kv a {{
            color: var(--accent);
            text-decoration: none;
        }}

        .meta-kv a:hover {{
            text-decoration: underline;
        }}

        /* Images Grid - Natural size, no scaling */
        .images-grid {{
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }}

        .image-item {{
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            max-width: 100%;
        }}

        .image-item:hover {{
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
        }}

        .image-item img {{
            display: block;
            max-width: 300px;
            max-height: 200px;
            width: auto;
            height: auto;
            background: var(--bg-tertiary);
        }}

        .image-item figcaption {{
            padding: 0.75rem;
            font-size: 0.8rem;
            max-width: 300px;
        }}

        .image-item .img-label {{
            display: block;
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 0.25rem;
        }}

        .image-item code {{
            display: block;
            font-size: 0.7rem;
            color: var(--text-secondary);
            background: transparent;
            padding: 0;
            word-break: break-all;
        }}

        /* JSON-LD Blocks */
        .json-ld-block {{
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }}

        .json-ld-block:last-child {{
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }}

        .json-ld-block h4 {{
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }}

        .json-ld-block.json-ld-error h4 {{
            color: var(--error);
        }}

        .valid-badge {{
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            padding: 0.2em 0.6em;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 600;
        }}

        .error-badge {{
            background: rgba(239, 68, 68, 0.15);
            color: var(--error);
            padding: 0.2em 0.6em;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 600;
        }}

        .json-ld-block pre {{
            max-height: 300px;
        }}

        .empty-state {{
            color: var(--text-secondary);
            font-style: italic;
        }}

        /* Responsive */
        @media (max-width: 768px) {{
            body {{
                padding: 1rem;
            }}

            h1 {{
                font-size: 1.5rem;
            }}

            .card {{
                padding: 1rem;
            }}

            .prop-cell {{
                width: 120px;
            }}

            th, td {{
                padding: 0.5rem 0.75rem;
            }}

            .image-item img {{
                max-width: 200px;
                max-height: 150px;
            }}

            .image-item figcaption {{
                max-width: 200px;
            }}
        }}
    </style>
</head>
<body>
    <div class="container">
        <h1>toolbox.jozapf.de | Meta-Debug-Web v3.2.0</h1>
        <p class="subtitle">Analyze Open Graph, Meta Tags, and Structured Data</p>

        <div class="card">
            <h2>Analyze URL</h2>
            <form method="get" action="">
                <input type="text" name="url" value="{escaped_url}" placeholder="https://example.com">
                <button type="submit">Analyze</button>
            </form>
            <p class="form-hint">
                This tool fetches the specified page and extracts OG tags, meta tags, <strong>JSON-LD (Structured Data)</strong>, images, and a text preview.
                <strong>No files are stored.</strong>
            </p>
        </div>

        {error_block}
"""

    if resp is not None and not error_message:
        # Count JSON-LD blocks for summary
        json_ld_count = len(json_ld_blocks)
        json_ld_valid = sum(1 for b in json_ld_blocks if b.get("valid"))
        json_ld_invalid = json_ld_count - json_ld_valid
        
        json_ld_summary = f"{json_ld_count} block(s)"
        if json_ld_invalid > 0:
            json_ld_summary += f" ({json_ld_invalid} invalid)"

        html_page += f"""
        <div class="card">
            <h2>Page Information</h2>
            <dl class="meta-kv">
                <dt>Title</dt>
                <dd>{escaped_title}</dd>
                <dt>Original URL</dt>
                <dd><a href="{escaped_url}" target="_blank" rel="noopener">{escaped_url}</a></dd>
                <dt>Final URL (after redirects)</dt>
                <dd><a href="{escaped_final_url}" target="_blank" rel="noopener">{escaped_final_url}</a></dd>
                <dt>HTTP Status Code</dt>
                <dd><code>{status_code}</code></dd>
                <dt>JSON-LD Schemas</dt>
                <dd>{json_ld_summary}</dd>
            </dl>
        </div>

        <div class="card">
            <h2>Meta &amp; Structured Data Images</h2>
            <div class="images-grid">
                {image_blocks_html or "<p class='empty-state'>No meta images found (og:image, favicon, JSON-LD images).</p>"}
            </div>
        </div>

        <div class="card">
            <h2>Open Graph Tags</h2>
            <table>
                <thead>
                    <tr><th>Property</th><th>Content</th></tr>
                </thead>
                <tbody>
                    {og_rows_html or "<tr><td colspan='2' class='empty-state'>No OG tags found.</td></tr>"}
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Meta Tags</h2>
            <table>
                <thead>
                    <tr><th>Name / Property</th><th>Content</th></tr>
                </thead>
                <tbody>
                    {meta_rows_html or "<tr><td colspan='2' class='empty-state'>No meta tags found.</td></tr>"}
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Structured Data (JSON-LD)</h2>
            {json_ld_html}
        </div>

        <div class="card">
            <h2>Text Preview (first 2000 characters)</h2>
            <pre>{text_preview_html}</pre>
        </div>
"""

    html_page += """
    </div>
</body>
</html>
"""
    return html_page


def main():
    form = cgi.FieldStorage()
    url = form.getfirst("url", "").strip()

    # Show form only if no URL provided
    if not url:
        page = build_html_page(url="")
        sys.stdout.write(page)
        return

    # Fetch page
    try:
        resp = fetch_url(url, timeout=10)
    except Exception as e:
        page = build_html_page(
            url=url,
            error_message=f"Error fetching URL: {e}"
        )
        sys.stdout.write(page)
        return

    # Parse HTML
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
        # Fallback output with trace if parsing fails
        sys.stdout.write("Content-Type: text/plain; charset=utf-8\n\n")
        sys.stdout.write("Error during HTML analysis:\n")
        traceback.print_exc(file=sys.stdout)


if __name__ == "__main__":
    main()
