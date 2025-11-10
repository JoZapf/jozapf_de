// Language detection and HTML lang attribute update
(function() {
  'use strict';
  
  // Detect current language from URL path
  const currentPath = window.location.pathname;
  const isEnglish = currentPath.startsWith('/en/') || currentPath === '/en';
  
  // Update HTML lang attribute
  const htmlEl = document.documentElement;
  if (isEnglish && htmlEl.lang !== 'en') {
    htmlEl.lang = 'en';
  } else if (!isEnglish && htmlEl.lang !== 'de') {
    htmlEl.lang = 'de';
  }
  
  // Update active state on language toggle buttons (if any page changes dynamically)
  const langToggles = document.querySelectorAll('.lang-toggle');
  langToggles.forEach(toggle => {
    const href = toggle.getAttribute('href');
    if (href === '/' && !isEnglish) {
      toggle.classList.add('active');
      toggle.setAttribute('aria-current', 'true');
    } else if (href === '/en/' && isEnglish) {
      toggle.classList.add('active');
      toggle.setAttribute('aria-current', 'true');
    } else {
      toggle.classList.remove('active');
      toggle.removeAttribute('aria-current');
    }
  });
})();
