#!/bin/bash
# =============================================================================
# Security Functional Test Suite — jozapf.de Kontaktformular
# Version: 1.0.0 (2026-03-25)
# Ausführen: bash security-tests.sh
# Voraussetzung: curl, jq (optional für JSON-Parsing)
# =============================================================================

BASE_URL="https://jozapf.de/assets/php"
PASS=0
FAIL=0
WARN=0

pass() { echo "  ✅ PASS: $1"; ((PASS++)); }
fail() { echo "  ❌ FAIL: $1"; ((FAIL++)); }
warn() { echo "  ⚠️  WARN: $1"; ((WARN++)); }

check_http() {
    local url="$1" expected="$2" label="$3" method="${4:-GET}"
    local code
    if [ "$method" = "OPTIONS" ]; then
        code=$(curl -s -o /dev/null -w "%{http_code}" -X OPTIONS "$url" 2>/dev/null)
    elif [ "$method" = "POST" ]; then
        code=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$url" 2>/dev/null)
    else
        code=$(curl -s -o /dev/null -w "%{http_code}" "$url" 2>/dev/null)
    fi
    if [ "$code" = "$expected" ]; then
        pass "$label → $code"
    else
        fail "$label → $code (erwartet: $expected)"
    fi
}

echo "=== SECURITY FUNCTIONAL TESTS — $(date) ==="
echo ""

# ─────────────────────────────────────────────
echo "── T1: .htaccess — Gesperrte Dateien (Phase 1, HF-04/MF-01/MF-05) ──"
# Alle müssen 403 Forbidden zurückgeben
check_http "$BASE_URL/_probe.php"              "403" "HF-04: _probe.php"
check_http "$BASE_URL/_smtp_probe.php"         "403" "HF-04: _smtp_probe.php"
check_http "$BASE_URL/selftest.php"            "403" "HF-04: selftest.php"
check_http "$BASE_URL/_env_check.php"          "403" "HF-04: _env_check.php"
check_http "$BASE_URL/_autoload_check.php"     "403" "HF-04: _autoload_check.php"
check_http "$BASE_URL/health-check.php"        "403" "HF-04: health-check.php"
check_http "$BASE_URL/mail-transport-diag.php" "403" "HF-04: mail-transport-diag.php"
check_http "$BASE_URL/debug_session.php"       "403" "NEU: debug_session.php"
check_http "$BASE_URL/.env.compose"            "403" "MF-01: .env.compose"
check_http "$BASE_URL/app.env"                 "403" "MF-01: app.env"
check_http "$BASE_URL/.env.prod.example.v3"    "403" "MF-05: .env.prod.example.v3"
check_http "$BASE_URL/.env.prod"               "403" "Secrets: .env.prod"
check_http "$BASE_URL/AbuseLogger.php"         "403" "Klasse: AbuseLogger.php"
check_http "$BASE_URL/BlocklistManager.php"    "403" "Klasse: BlocklistManager.php"
check_http "$BASE_URL/ExtendedLogger.php"      "403" "Klasse: ExtendedLogger.php"
check_http "$BASE_URL/helpers.php"             "403" "Klasse: helpers.php"
check_http "$BASE_URL/LoginRateLimiter.php"    "403" "Klasse: LoginRateLimiter.php"
check_http "$BASE_URL/blocklist.txt"           "403" "Daten: blocklist.txt"
echo ""

# ─────────────────────────────────────────────
echo "── T2: .htaccess — Erreichbare Endpoints ──"
# Diese MÜSSEN erreichbar sein (200, 302/303 Redirect, oder 429 Rate-Limited)
# 429 ist OK — bedeutet Rate-Limiting greift (nicht 403 = gesperrt)
HANDLER_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE_URL/contact-php-handler.php" 2>/dev/null)
if [ "$HANDLER_CODE" = "200" ] || [ "$HANDLER_CODE" = "429" ] || [ "$HANDLER_CODE" = "422" ]; then
    pass "Handler POST erreichbar → $HANDLER_CODE (429=Rate-Limited, 422=Validation)"
elif [ "$HANDLER_CODE" = "403" ]; then
    fail "Handler POST gesperrt → 403 (sollte erreichbar sein!)"
else
    warn "Handler POST unerwarteter Code → $HANDLER_CODE"
fi
check_http "$BASE_URL/dashboard-login.php"     "200" "Dashboard-Login erreichbar"
echo ""

# ─────────────────────────────────────────────
echo "── T3: CORS-Header (Phase 2, KF-02) ──"
CORS_HEADER=$(curl -s -D - -o /dev/null -X OPTIONS "$BASE_URL/contact-php-handler.php" 2>/dev/null | grep -i "access-control-allow-origin")
if echo "$CORS_HEADER" | grep -q "https://jozapf.de"; then
    pass "CORS Origin = https://jozapf.de"
elif echo "$CORS_HEADER" | grep -q "\*"; then
    fail "CORS Origin = * (Wildcard — UNSICHER!)"
else
    warn "CORS-Header nicht gefunden oder unerwartet: $CORS_HEADER"
fi
echo ""

# ─────────────────────────────────────────────
echo "── T4: CSRF-Token Init-Endpoint (Phase 3+4, KF-03/HF-01) ──"
INIT_RESPONSE=$(curl -s -c /tmp/jozapf_cookies.txt "$BASE_URL/contact-php-handler.php?init=1" 2>/dev/null)
if echo "$INIT_RESPONSE" | grep -q "csrf_token"; then
    pass "Init-Endpoint liefert csrf_token"
else
    fail "Init-Endpoint liefert kein csrf_token: $INIT_RESPONSE"
fi
if echo "$INIT_RESPONSE" | grep -q "captcha"; then
    pass "Init-Endpoint liefert captcha-Frage"
else
    fail "Init-Endpoint liefert keine captcha-Frage"
fi
echo ""

# ─────────────────────────────────────────────
echo "── T5: CSRF-Ablehnung ohne Token (Phase 3, KF-03) ──"
# POST ohne CSRF-Token — im strikten Modus sollte das 403 geben
# Im Migrations-Modus wird es durchgelassen (kein Session-Token vorhanden)
CSRF_TEST=$(curl -s -X POST "$BASE_URL/contact-php-handler.php" \
    -d "firstName=Test&lastName=Test&email=test@test.com&message=test&privacy=on&captchaAnswer=99" \
    2>/dev/null)
if echo "$CSRF_TEST" | grep -qi "security token"; then
    pass "CSRF: POST ohne Token wird abgelehnt (strikter Modus)"
else
    warn "CSRF: POST ohne Token nicht explizit abgelehnt (Migrations-Modus aktiv?)"
fi
echo ""

# ─────────────────────────────────────────────
echo "── T6: Cross-Origin Ablehnung (Phase 2, KF-02) ──"
# Simuliere einen Request von einer fremden Domain
CROSS_ORIGIN=$(curl -s -o /dev/null -w "%{http_code}" -X POST \
    -H "Origin: https://evil.com" \
    "$BASE_URL/contact-php-handler.php" \
    -d "firstName=Spam&lastName=Bot&email=spam@evil.com&message=test&privacy=on&captchaAnswer=1" \
    2>/dev/null)
# Server antwortet zwar mit 200, aber der CORS-Header erlaubt evil.com nicht
# → Browser blockiert die Response. Server-seitig wird die Mail trotzdem verarbeitet
# bis CSRF im strikten Modus ist. Daher prüfen wir den Response-Header:
CROSS_CORS=$(curl -s -D - -o /dev/null -X POST \
    -H "Origin: https://evil.com" \
    "$BASE_URL/contact-php-handler.php" 2>/dev/null | grep -i "access-control-allow-origin")
if echo "$CROSS_CORS" | grep -q "https://jozapf.de"; then
    pass "Cross-Origin: Server antwortet mit https://jozapf.de (evil.com wird vom Browser blockiert)"
elif echo "$CROSS_CORS" | grep -q "evil.com"; then
    fail "Cross-Origin: Server reflektiert evil.com als erlaubte Origin!"
else
    warn "Cross-Origin: CORS-Header unerwartet: $CROSS_CORS"
fi
echo ""

# ─────────────────────────────────────────────
echo "── T7: Directory Listing (Phase 1, MF-01) ──"
DIR_TEST=$(curl -s "$BASE_URL/" 2>/dev/null)
if echo "$DIR_TEST" | grep -qi "index of"; then
    fail "Directory Listing aktiv — Options -Indexes fehlt!"
else
    pass "Directory Listing deaktiviert"
fi
echo ""

# ─────────────────────────────────────────────
echo "── T8: Markdown-Dateien gesperrt ──"
check_http "$BASE_URL/SECURITY-FIX-KONZEPT.md"  "403" "MD: SECURITY-FIX-KONZEPT.md"
check_http "$BASE_URL/SECURITY-VORHER-NACHHER.md" "403" "MD: SECURITY-VORHER-NACHHER.md"
check_http "$BASE_URL/PLANTUML-BRIEFING.md"      "403" "MD: PLANTUML-BRIEFING.md"
echo ""

# ─────────────────────────────────────────────
echo "── T9: Session-Härtung (Phase 3, NF-01) ──"
SESSION_HEADERS=$(curl -s -D - -o /dev/null "$BASE_URL/contact-php-handler.php?init=1" 2>/dev/null)
if echo "$SESSION_HEADERS" | grep -qi "httponly"; then
    pass "Session-Cookie: HttpOnly gesetzt"
else
    warn "Session-Cookie: HttpOnly nicht erkannt"
fi
if echo "$SESSION_HEADERS" | grep -qi "samesite=strict"; then
    pass "Session-Cookie: SameSite=Strict"
else
    warn "Session-Cookie: SameSite=Strict nicht erkannt"
fi
if echo "$SESSION_HEADERS" | grep -qi "secure"; then
    pass "Session-Cookie: Secure-Flag gesetzt"
else
    warn "Session-Cookie: Secure-Flag nicht erkannt"
fi
echo ""

# =============================================================================
echo "═══════════════════════════════════════════"
echo "  ERGEBNIS: $PASS bestanden, $FAIL fehlgeschlagen, $WARN Warnungen"
echo "═══════════════════════════════════════════"
echo ""

if [ $FAIL -gt 0 ]; then
    echo "  ‼️  $FAIL Tests fehlgeschlagen — Handlungsbedarf!"
    exit 1
elif [ $WARN -gt 0 ]; then
    echo "  ⚠️  Alle Tests bestanden, aber $WARN Warnungen prüfen."
    exit 0
else
    echo "  🎉 Alle Tests bestanden!"
    exit 0
fi

# Aufräumen
rm -f /tmp/jozapf_cookies.txt
