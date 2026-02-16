// public/assets/js/privacy-modal.js
// Global Privacy Policy Modal Handler (independent of contact form)
// Uses event delegation to support multiple privacy links

(function initPrivacyModal() {
  let initialized = false;

  function setupPrivacyModal() {
    if (initialized) return;

    const modal = document.getElementById('privacyModal');
    const closeBtn = document.getElementById('closePrivacyModal');
    const iframe = document.getElementById('privacyFrame');

    // Modal elements must exist
    if (!modal || !closeBtn || !iframe) {
      return;
    }

    // Event delegation: Handle ALL links with data-privacy-url attribute
    document.addEventListener('click', (e) => {
      const privacyLink = e.target.closest('[data-privacy-url]');
      if (!privacyLink) return;

      e.preventDefault();
      const url = privacyLink.dataset.privacyUrl || '/privacy.html';
      iframe.src = url;
      modal.style.display = 'flex';
    });

    // Close button click handler
    closeBtn.addEventListener('click', () => {
      modal.style.display = 'none';
      iframe.src = '';
    });

    // Modal background click handler (close on outside click)
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.style.display = 'none';
        iframe.src = '';
      }
    });

    // Escape key handler
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modal.style.display === 'flex') {
        modal.style.display = 'none';
        iframe.src = '';
      }
    });

    initialized = true;
  }

  // Setup when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupPrivacyModal);
  } else {
    setupPrivacyModal();
  }

  // If modal loads after script, retry
  const observer = new MutationObserver(() => {
    if (!initialized && document.getElementById('privacyModal')) {
      setupPrivacyModal();
      observer.disconnect();
    }
  });

  observer.observe(document.body, { childList: true, subtree: true });
})();
