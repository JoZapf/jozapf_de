// assets/js/contact-form-logic.js - FINAL FIXED VERSION
const $  = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

let captchaSolution = null;

function ensureHiddenAnswerInput(form) {
  let hidden = $('[name="captcha_answer"]', form);
  if (!hidden) {
    hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'captcha_answer';
    form.appendChild(hidden);
  }
  return hidden;
}

function generateCaptcha(form) {
  const a = Math.floor(Math.random() * 7) + 6; // 6..12
  const b = Math.floor(Math.random() * 6) + 1; // 1..6
  const op = Math.random() < 0.5 ? '+' : '−';
  captchaSolution = op === '+' ? a + b : a - b;
  if (captchaSolution < 0) { captchaSolution = a + b; }

  const q = $('#captchaQuestion', form?.ownerDocument || document);
  if (q) q.textContent = `${a} ${op} ${b} = ?`;
}

function setCaptchaOnForm(form) {
  const hidden = ensureHiddenAnswerInput(form);
  hidden.value = captchaSolution != null ? String(captchaSolution) : '';
}

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

async function submitForm(form) {
  const successBox = $('#cf-success');
  const errorBox   = $('#cf-error');
  const errorText  = $('#cf-error-text');
  hide(successBox); hide(errorBox);

  const firstName = $('#firstName', form);
  const lastName  = $('#lastName',  form);
  const email     = $('#email',     form);
  const phone     = $('#phone',     form);
  const subject   = $('#subject',   form);
  const message   = $('#message',   form);
  const privacy   = $('#privacy',   form);
  const captchaA  = $('#captchaAnswer', form);

  [firstName, lastName, email, subject, message, captchaA, privacy].forEach(clearFieldState);

  let hasErr = false;
  if (!firstName?.value.trim()) { setFieldInvalid(firstName); hasErr = true; }
  if (!lastName?.value.trim())  { setFieldInvalid(lastName);  hasErr = true; }
  if (!email?.value.trim() || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email.value)) { setFieldInvalid(email); hasErr = true; }
  if (!subject?.value.trim())   { setFieldInvalid(subject);   hasErr = true; }
  if (!message?.value.trim())   { setFieldInvalid(message);   hasErr = true; }
  if (!privacy?.checked)        { setFieldInvalid(privacy);   hasErr = true; }

  const userAns = Number(captchaA?.value);
  if (!Number.isFinite(userAns) || String(captchaA?.value ?? '').trim() === '') {
    setFieldInvalid(captchaA, 'Please solve the math problem.');
    hasErr = true;
  } else if (captchaSolution != null && userAns !== captchaSolution) {
    setFieldInvalid(captchaA, 'Incorrect answer. New problem generated.');
    generateCaptcha(form); setCaptchaOnForm(form);
    hasErr = true;
  }

  if (hasErr) {
    show(errorBox);
    if (errorText) errorText.textContent = 'Please correct the marked fields.';
    return;
  }

  setCaptchaOnForm(form);
  const fd = new FormData(form);
  const handlerURL = 'assets/php/contact-php-handler.php';

  try {
    const submitBtn = $('#submitBtn', form);
    if (submitBtn) submitBtn.disabled = true;

    const res = await fetch(handlerURL, {
      method: 'POST',
      body: fd,
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
    });

    let data = null;
    try { data = await res.json(); } catch { /* no-op */ }

    // ✅ FIXED: Check for data.success and ensure proper CSS classes
    if (res.ok && data && data.success === true) {
      form.reset();
      generateCaptcha(form); setCaptchaOnForm(form);
      
      // ✅ GREEN SUCCESS - Remove error class, add success class
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
      
      // Set success message from server
      const successText = successBox.querySelector('p, div, [id*="text"]');
      if (successText && data.message) {
        successText.textContent = data.message;
      } else if (successBox) {
        // Fallback: Update the entire success box content
        successBox.innerHTML = '<p>' + (data.message || 'Thank you for your message! We will get back to you shortly.') + '</p>';
      }
      
    } else {
      // ❌ RED ERROR
      const msg = (data && (data.error || data.message)) || 'Failed to send message. Please try again later.';
      if (errorText) errorText.textContent = msg;
      
      // Ensure error box has error class, success box has success class
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

      if (data && data.fields) {
        if (data.fields.firstName === false || data.fields.name === false) setFieldInvalid(firstName);
        if (data.fields.lastName  === false) setFieldInvalid(lastName);
        if (data.fields.email     === false) setFieldInvalid(email);
        if (data.fields.subject   === false) setFieldInvalid(subject);
        if (data.fields.message   === false) setFieldInvalid(message);
        if (data.fields.captcha   === false || data.fields.captchaAnswer === false) setFieldInvalid(captchaA);
        if (data.fields.privacy   === false) setFieldInvalid(privacy);
      }
    }
  } catch (e) {
    if (errorText) errorText.textContent = 'Network error. Please try again later.';
    
    // Ensure error styling
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

// Modal-Funktionalität für Privacy Policy
function initPrivacyModal() {
  const privacyLink = document.getElementById('privacyLink');
  const modal = document.getElementById('privacyModal');
  const closeBtn = document.getElementById('closePrivacyModal');
  const iframe = document.getElementById('privacyFrame');

  if (!privacyLink || !modal) return;

  privacyLink.addEventListener('click', (e) => {
    e.preventDefault();
    const url = privacyLink.dataset.privacyUrl || '/test11/privacy.html';
    iframe.src = url;
    modal.style.display = 'flex';
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', () => {
      modal.style.display = 'none';
      iframe.src = '';
    });
  }

  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      modal.style.display = 'none';
      iframe.src = '';
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.style.display === 'flex') {
      modal.style.display = 'none';
      iframe.src = '';
    }
  });
}

// Öffentliche Initialisierung
export function initContactForm(root = document) {
  const form = $('#contactForm', root);
  if (!form || form.dataset.initialized === '1') return;

  generateCaptcha(form);
  setCaptchaOnForm(form);

  const refreshBtn = $('#captchaRefresh', form);
  if (refreshBtn) {
    refreshBtn.addEventListener('click', (e) => {
      e.preventDefault();
      generateCaptcha(form); setCaptchaOnForm(form);
      const ans = $('#captchaAnswer', form);
      if (ans) { ans.value = ''; clearFieldState(ans); }
    });
  }

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    submitForm(form);
  });

  // Privacy Modal initialisieren
  initPrivacyModal();

  form.dataset.initialized = '1';
}

// Fallback
if (typeof window !== 'undefined') {
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('contactForm');
    if (form && form.dataset.initialized !== '1') {
      try { initContactForm(document); } catch { /* noop */ }
    }
  });
}
