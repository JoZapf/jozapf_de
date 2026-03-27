/**
 * Contact Form Logic - V2.0 SERVER-BASED CSRF + CAPTCHA
 * ======================================================
 * 
 * @version 2.0.0
 * @date 2026-03-27
 * 
 * Changelog v2.0.0 (2026-03-27):
 * - CSRF-Token vom Server holen (nicht mehr client-seitig)
 * - Captcha-Aufgabe vom Server holen (nicht mehr client-seitig generiert)
 * - Hidden-Field "captcha_answer" entfernt (Lösung nur in Server-Session)
 * - Hidden-Field "csrf_token" hinzugefügt
 * - Client-seitige Captcha-Validierung entfernt (Server validiert)
 * - fetchFormInit() für ?init=1 Endpoint
 * 
 * Changelog v1.x (vorher):
 * - Client-seitige Captcha-Generierung (unsicher)
 * - Captcha-Lösung als Hidden-Field (Bot konnte auslesen)
 */

const $  = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

// Handler-URL (absolut für alle Unterseiten)
const HANDLER_URL = '/assets/php/contact-php-handler.php';

// ============================================================================
// FORM INIT — Holt CSRF-Token + Captcha vom Server
// ============================================================================

/**
 * Ruft den Init-Endpoint auf und gibt CSRF-Token + Captcha zurück.
 * Setzt auch das Session-Cookie (credentials: 'same-origin').
 * 
 * @returns {Promise<{csrf_token: string, captcha: {question: string, a: number, b: number}}|null>}
 */
async function fetchFormInit() {
  try {
    const res = await fetch(HANDLER_URL + '?init=1', {
      method: 'GET',
      credentials: 'same-origin', // WICHTIG: Session-Cookie setzen/senden
      headers: { 'Accept': 'application/json' }
    });
    
    if (!res.ok) {
      console.error('[ContactForm] Init failed:', res.status, res.statusText);
      return null;
    }
    
    const json = await res.json();
    if (!json.success || !json.data) {
      console.error('[ContactForm] Init response invalid:', json);
      return null;
    }
    
    return json.data; // { csrf_token, captcha: { question, a, b } }
  } catch (e) {
    console.error('[ContactForm] Init error:', e);
    return null;
  }
}

/**
 * Wendet Init-Daten auf das Formular an:
 * - Setzt CSRF-Token in Hidden-Field
 * - Zeigt Captcha-Frage an
 * 
 * @param {HTMLFormElement} form 
 * @param {object} initData - Von fetchFormInit()
 */
function applyInitData(form, initData) {
  if (!initData) return false;
  
  // CSRF-Token Hidden-Field erstellen/aktualisieren
  let csrfInput = form.querySelector('[name="csrf_token"]');
  if (!csrfInput) {
    csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    form.appendChild(csrfInput);
  }
  csrfInput.value = initData.csrf_token;
  
  // Captcha-Frage anzeigen
  const captchaQ = $('#captchaQuestion', form.ownerDocument || document);
  if (captchaQ && initData.captcha?.question) {
    captchaQ.textContent = initData.captcha.question;
  }
  
  return true;
}

// ============================================================================
// UI HELPERS
// ============================================================================

function show(el) { if (el) el.classList.remove('d-none'); }
function hide(el) { if (el) el.classList.add('d-none'); }

function setFieldInvalid(input, message) {
  if (!input) return;
  input.classList.add('is-invalid');
  if (message) {
    const fb = input.nextElementSibling;
    if (fb && fb.classList.contains('invalid-feedback')) fb.textContent = message;
  }
}

function clearFieldState(input) {
  if (!input) return;
  input.classList.remove('is-invalid');
}

// ============================================================================
// FORM SUBMIT
// ============================================================================

async function submitForm(form) {
  const successBox = $('#cf-success');
  const errorBox   = $('#cf-error');
  const errorText  = $('#cf-error-text');
  hide(successBox); hide(errorBox);

  // Felder referenzieren
  const firstName = $('#firstName', form);
  const lastName  = $('#lastName',  form);
  const email     = $('#email',     form);
  const phone     = $('#phone',     form);
  const subject   = $('#subject',   form);
  const message   = $('#message',   form);
  const privacy   = $('#privacy',   form);
  const captchaA  = $('#captchaAnswer', form);

  // Alle Felder zurücksetzen
  [firstName, lastName, email, subject, message, captchaA, privacy].forEach(clearFieldState);

  // Client-seitige Validierung (Pflichtfelder)
  let hasErr = false;
  if (!firstName?.value.trim()) { setFieldInvalid(firstName); hasErr = true; }
  if (!lastName?.value.trim())  { setFieldInvalid(lastName);  hasErr = true; }
  if (!email?.value.trim() || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email.value)) { 
    setFieldInvalid(email); hasErr = true; 
  }
  if (!subject?.value.trim())   { setFieldInvalid(subject);   hasErr = true; }
  if (!message?.value.trim())   { setFieldInvalid(message);   hasErr = true; }
  if (!privacy?.checked)        { setFieldInvalid(privacy);   hasErr = true; }

  // Captcha: Nur prüfen ob ausgefüllt (Server validiert die Lösung!)
  const userAns = String(captchaA?.value ?? '').trim();
  if (userAns === '' || !Number.isFinite(Number(userAns))) {
    setFieldInvalid(captchaA, 'Please solve the math problem.');
    hasErr = true;
  }

  if (hasErr) {
    show(errorBox);
    if (errorText) errorText.textContent = 'Please correct the marked fields.';
    return;
  }

  // FormData erstellen (enthält CSRF-Token aus Hidden-Field)
  const fd = new FormData(form);

  try {
    const submitBtn = $('#submitBtn', form);
    if (submitBtn) submitBtn.disabled = true;

    const res = await fetch(HANDLER_URL, {
      method: 'POST',
      body: fd,
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin', // Session-Cookie mitsenden
    });

    let data = null;
    try { data = await res.json(); } catch { /* no-op */ }

    if (res.ok && data && data.success === true) {
      // ✅ ERFOLG
      form.reset();
      
      // Neues CSRF-Token + Captcha holen (für nächste Nachricht)
      const newInit = await fetchFormInit();
      applyInitData(form, newInit);
      
      // Success-Box stylen
      if (successBox) {
        successBox.classList.remove('cf-error');
        successBox.classList.add('cf-success');
      }
      if (errorBox) {
        errorBox.classList.remove('cf-success');
        errorBox.classList.add('cf-error');
      }
      
      show(successBox); 
      hide(errorBox);
      
      // Erfolgsmeldung vom Server
      const successText = successBox?.querySelector('p, div, [id*="text"]');
      if (successText && data.message) {
        successText.textContent = data.message;
      } else if (successBox) {
        successBox.innerHTML = '<p>' + (data.message || 'Thank you for your message! We will get back to you shortly.') + '</p>';
      }
      
    } else {
      // ❌ FEHLER
      const msg = (data && (data.error || data.message)) || 'Failed to send message. Please try again later.';
      if (errorText) errorText.textContent = msg;
      
      // Error-Box stylen
      if (errorBox) {
        errorBox.classList.remove('cf-success');
        errorBox.classList.add('cf-error');
      }
      if (successBox) {
        successBox.classList.remove('cf-error');
        successBox.classList.add('cf-success');
      }
      
      show(errorBox); 
      hide(successBox);

      // Feld-spezifische Fehler markieren
      if (data && data.fields) {
        if (data.fields.firstName === false || data.fields.name === false) setFieldInvalid(firstName);
        if (data.fields.lastName  === false) setFieldInvalid(lastName);
        if (data.fields.email     === false) setFieldInvalid(email);
        if (data.fields.subject   === false) setFieldInvalid(subject);
        if (data.fields.message   === false) setFieldInvalid(message);
        if (data.fields.captcha   === false || data.fields.captchaAnswer === false) setFieldInvalid(captchaA);
        if (data.fields.privacy   === false) setFieldInvalid(privacy);
      }
      
      // Bei CSRF/Captcha-Fehler: Neues Token+Captcha holen
      if (res.status === 403 || res.status === 422) {
        const newInit = await fetchFormInit();
        applyInitData(form, newInit);
        if (captchaA) captchaA.value = '';
      }
    }
  } catch (e) {
    console.error('[ContactForm] Submit error:', e);
    if (errorText) errorText.textContent = 'Network error. Please try again later.';
    
    if (errorBox) {
      errorBox.classList.remove('cf-success');
      errorBox.classList.add('cf-error');
    }
    if (successBox) {
      successBox.classList.remove('cf-error');
      successBox.classList.add('cf-success');
    }
    
    show(errorBox); 
    hide(successBox);
  } finally {
    const submitBtn = $('#submitBtn', form);
    if (submitBtn) submitBtn.disabled = false;
  }
}

// ============================================================================
// PUBLIC INIT
// ============================================================================

/**
 * Initialisiert das Kontaktformular.
 * Holt CSRF-Token + Captcha vom Server.
 * 
 * @param {Document|HTMLElement} root 
 */
export async function initContactForm(root = document) {
  const form = $('#contactForm', root);
  if (!form || form.dataset.initialized === '1') return;

  // Guard: Formular als "in Initialisierung" markieren (verhindert Doppelaufruf)
  form.dataset.initialized = '1';

  // CSRF-Token + Captcha vom Server holen
  const initData = await fetchFormInit();
  if (!initData) {
    console.error('[ContactForm] Could not initialize form - server unreachable');
    // Formular bleibt ohne Captcha-Frage, Submit wird Server-seitig scheitern
  }
  applyInitData(form, initData);

  // Captcha-Refresh Button
  const refreshBtn = $('#captchaRefresh', form);
  if (refreshBtn) {
    refreshBtn.addEventListener('click', async (e) => {
      e.preventDefault();
      
      // Neues CSRF-Token + Captcha vom Server
      const newInit = await fetchFormInit();
      applyInitData(form, newInit);
      
      // Captcha-Antwort-Feld leeren
      const ans = $('#captchaAnswer', form);
      if (ans) { 
        ans.value = ''; 
        clearFieldState(ans); 
      }
    });
  }

  // Submit-Handler
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    submitForm(form);
  });
}

// ============================================================================
// FALLBACK (DOMContentLoaded)
// ============================================================================

if (typeof window !== 'undefined') {
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('contactForm');
    if (form && form.dataset.initialized !== '1') {
      try { 
        initContactForm(document); 
      } catch (e) { 
        console.error('[ContactForm] Auto-init failed:', e);
      }
    }
  });
}
