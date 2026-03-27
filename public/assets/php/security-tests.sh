#!/bin/bash
# =============================================================================
# Security Functional Test Suite — jozapf.de Kontaktformular
# Version: 2.0.0 (2026-03-27)
# Ausführen: bash security-tests.sh
# Voraussetzung: curl, jq (optional für JSON-Parsing)
#
# Changelog v2.0.0 (2026-03-27):
#   - T10: Blacklist-Dateien gesperrt (.txt via .htaccess v2.1.0)
#   - T11: Spam-Prefix-Blocking (spam@evil.com, test@test.com)
#   - T12: Domain-Blacklist (mailinator.com, guerrillamail.com)
#   - T13: Disposable API Check (DeBounce)
#
# Changelog v1.0.0 (2026-03-25):
#   - Initial: T1–T9 (Security-Hardening Phase 1–6)
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

# Helper: POST ans Kontaktformular mit Session (Init → Submit)
# Gibt den JSON-Response zurück
submit_form() {
    local email="$1" firstname="${2:-Test}" lastname="${3:-User}" message="${4:-Security test}"
    local cookie_jar=$(mktemp)
    trap "rm -f $cookie_jar" RETURN

    # 1) Init: CSRF-Token + Captcha holen
    local init=$(curl -s -c "$cookie_jar" "$BASE_URL/contact-php-handler.php?init=1" 2>/dev/null)
    local csrf=$(echo "$init" | grep -o '"csrf_token":"[^"]*"' | cut -d'"' -f4)
    local captcha_a=$(echo "$init" | grep -o '"a":[0-9]*' | cut -d: -f2)
    local captcha_b=$(echo "$init" | grep -o '"b":[0-9]*' | cut -d: -f2)
    local answer=$((captcha_a + captcha_b))

    # 2) Submit
    curl -s -b "$cookie_jar" -X POST "$BASE_URL/contact-php-handler.php" \
        -d "firstName=${firstname}&lastName=${lastname}&email=${email}&message=${message}&privacy=on&captchaAnswer=${answer}&csrf_token=${csrf}&form_timestamp=$(( $(date +%s) - 5 ))" \
        2>/dev/null
}

echo "=== SECURITY FUNCTIONAL TESTS v2.0.0 — $(date) ==="
echo ""

# ─────────────────────────────────────────────
echo "── T1: .htaccess — Gesperrte Dateien (Phase 1, HF-04/MF-01/MF-05) ──"
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
HANDLER_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE_URL/contact-php-handler.php" 2>/dev/null)
if [ "$HANDLER_CODE" = "200" ] || [ "$HANDLER_CODE" = "429" ] || [ "$HANDLER_CODE" = "422" ]; then
    pass "Handler POST erreichbar → $HANDLER_CODE"
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
check_http "$BASE_URL/SECURITY-FIX-KONZEPT.md"     "403" "MD: SECURITY-FIX-KONZEPT.md"
check_http "$BASE_URL/SECURITY-VORHER-NACHHER.md"   "403" "MD: SECURITY-VORHER-NACHHER.md"
check_http "$BASE_URL/PLANTUML-BRIEFING.md"          "403" "MD: PLANTUML-BRIEFING.md"
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
# v2.0.0: Spam-Validierung Tests (Validator v2.2.0)
# =============================================================================

echo "── T10: Blacklist-Dateien gesperrt (.htaccess v2.1.0) ──"
check_http "$BASE_URL/data/domain-blacklist.txt"        "403" "Blacklist: domain-blacklist.txt"
check_http "$BASE_URL/data/domain-blacklist-custom.txt"  "403" "Blacklist: domain-blacklist-custom.txt"
check_http "$BASE_URL/data/blocklist.json"               "403" "Daten: blocklist.json"
check_http "$BASE_URL/data/whitelist.json"               "403" "Daten: whitelist.json"
echo ""

# ─────────────────────────────────────────────
echo "── T11: Spam-Prefix-Blocking (Validator v2.2.0, Schicht 1) ──"
echo "  (Sendet echte Formular-Submits mit Init→CSRF→Captcha-Flow)"

# Test: spam@evil.com → muss geblockt werden
SPAM_RESULT=$(submit_form "spam@evil.com" "Spam" "Test" "This is a spam test")
if echo "$SPAM_RESULT" | grep -qi '"success":false\|blocked\|spam'; then
    SPAM_SCORE=$(echo "$SPAM_RESULT" | grep -o '"spamScore":[0-9]*' | cut -d: -f2)
    pass "spam@evil.com geblockt (Score: ${SPAM_SCORE:-?})"
else
    fail "spam@evil.com NICHT geblockt: $SPAM_RESULT"
fi

# Test: test@test.com → muss geblockt werden
TEST_RESULT=$(submit_form "test@test.com" "Test" "User" "Testing the form")
if echo "$TEST_RESULT" | grep -qi '"success":false\|blocked\|spam'; then
    TEST_SCORE=$(echo "$TEST_RESULT" | grep -o '"spamScore":[0-9]*' | cut -d: -f2)
    pass "test@test.com geblockt (Score: ${TEST_SCORE:-?})"
else
    fail "test@test.com NICHT geblockt: $TEST_RESULT"
fi

# Test: fake@example.org → muss geblockt werden (Prefix + Blacklist)
FAKE_RESULT=$(submit_form "fake@example.org" "Fake" "Person" "Fake submission")
if echo "$FAKE_RESULT" | grep -qi '"success":false\|blocked\|spam'; then
    FAKE_SCORE=$(echo "$FAKE_RESULT" | grep -o '"spamScore":[0-9]*' | cut -d: -f2)
    pass "fake@example.org geblockt (Score: ${FAKE_SCORE:-?})"
else
    fail "fake@example.org NICHT geblockt: $FAKE_RESULT"
fi
echo ""

# ─────────────────────────────────────────────
echo "── T12: Domain-Blacklist (Validator v2.2.0, Schicht 2) ──"

# Test: user@mailinator.com → muss geblockt werden (Blacklist)
MAIL_RESULT=$(submit_form "user@mailinator.com" "John" "Doe" "Legit looking message from disposable")
if echo "$MAIL_RESULT" | grep -qi '"success":false\|blocked\|spam'; then
    MAIL_SCORE=$(echo "$MAIL_RESULT" | grep -o '"spamScore":[0-9]*' | cut -d: -f2)
    pass "user@mailinator.com geblockt (Score: ${MAIL_SCORE:-?})"
else
    fail "user@mailinator.com NICHT geblockt: $MAIL_RESULT"
fi

# Test: user@guerrillamail.com → muss geblockt werden (Blacklist)
GUER_RESULT=$(submit_form "user@guerrillamail.com" "Jane" "Doe" "Another disposable test")
if echo "$GUER_RESULT" | grep -qi '"success":false\|blocked\|spam'; then
    GUER_SCORE=$(echo "$GUER_RESULT" | grep -o '"spamScore":[0-9]*' | cut -d: -f2)
    pass "user@guerrillamail.com geblockt (Score: ${GUER_SCORE:-?})"
else
    fail "user@guerrillamail.com NICHT geblockt: $GUER_RESULT"
fi

# Test: user@yopmail.com → muss geblockt werden (Blacklist)
YOP_RESULT=$(submit_form "user@yopmail.com" "Max" "Mustermann" "Yopmail test message")
if echo "$YOP_RESULT" | grep -qi '"success":false\|blocked\|spam'; then
    YOP_SCORE=$(echo "$YOP_RESULT" | grep -o '"spamScore":[0-9]*' | cut -d: -f2)
    pass "user@yopmail.com geblockt (Score: ${YOP_SCORE:-?})"
else
    fail "user@yopmail.com NICHT geblockt: $YOP_RESULT"
fi
echo ""

# ─────────────────────────────────────────────
echo "── T13: DeBounce API Erreichbarkeit (Schicht 3) ──"
API_RESPONSE=$(curl -s --max-time 5 "https://disposable.debounce.io/?email=check@mailinator.com" 2>/dev/null)
if echo "$API_RESPONSE" | grep -qi '"disposable":"true"'; then
    pass "DeBounce API erreichbar + erkennt mailinator.com als disposable"
elif echo "$API_RESPONSE" | grep -qi "disposable"; then
    warn "DeBounce API erreichbar, aber unerwartete Antwort: $API_RESPONSE"
else
    warn "DeBounce API nicht erreichbar (Schicht 3 fällt auf Schicht 1+2 zurück)"
fi

API_LEGIT=$(curl -s --max-time 5 "https://disposable.debounce.io/?email=check@gmail.com" 2>/dev/null)
if echo "$API_LEGIT" | grep -qi '"disposable":"false"'; then
    pass "DeBounce API: gmail.com korrekt als nicht-disposable erkannt"
else
    warn "DeBounce API: gmail.com Antwort unerwartet: $API_LEGIT"
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
