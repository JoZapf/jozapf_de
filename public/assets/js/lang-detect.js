// Language detection, HTML lang attribute update, and language toggle URL rewriting
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
  
  // Rewrite language toggle href to point to the equivalent page in the other language
  // DE page: /some-page/ → EN toggle gets /en/some-page/
  // EN page: /en/some-page/ → DE toggle gets /some-page/
  const langToggles = document.querySelectorAll('.lang-toggle');
  langToggles.forEach(function(toggle) {
    if (isEnglish) {
      // Strip /en prefix to get the DE equivalent
      var dePath = currentPath.replace(/^\/en(\/|$)/, '/');
      toggle.setAttribute('href', dePath || '/');
    } else {
      // Prepend /en to get the EN equivalent
      var enPath = '/en' + (currentPath === '/' ? '/' : currentPath);
      toggle.setAttribute('href', enPath);
    }
  });
  
  // Update active state on language toggle buttons
  langToggles.forEach(function(toggle) {
    const href = toggle.getAttribute('href');
    if (href === '/' && !isEnglish) {
      toggle.classList.add('active');
      toggle.setAttribute('aria-current', 'true');
    } else if (href && href.startsWith('/en') && isEnglish) {
      toggle.classList.add('active');
      toggle.setAttribute('aria-current', 'true');
    } else {
      toggle.classList.remove('active');
      toggle.removeAttribute('aria-current');
    }
  });
})();
