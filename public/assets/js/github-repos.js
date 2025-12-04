/**
 * GitHub Repositories Fetcher
 * Fetches and displays public repositories from GitHub API
 * 
 * Optimierung 2025-12-04:
 * - Swiper wird lazy geladen (nur wenn Section sichtbar)
 * - Intersection Observer für Performance
 * - ~150 KB JS gespart beim initialen Seitenaufbau
 */

const GITHUB_USERNAME = 'JoZapf';
const GITHUB_API_URL = `https://api.github.com/users/${GITHUB_USERNAME}/repos`;
const MAX_REPOS = 12;
const CACHE_KEY = 'github_repos_cache';
const CACHE_DURATION = 1000 * 60 * 60; // 1 hour

// Swiper assets (lazy loaded)
const SWIPER_CSS_URL = '/assets/css/swiper-bundle.min.css';
const SWIPER_JS_URL = '/assets/js/swiper-bundle.min.js';

// Known bot/crawler user agents
const BOT_PATTERNS = [
  'googlebot',
  'bingbot',
  'slurp',
  'duckduckbot',
  'baiduspider',
  'yandexbot',
  'facebookexternalhit',
  'twitterbot',
  'linkedinbot',
  'whatsapp',
  'telegrambot',
  'applebot',
  'semrushbot',
  'ahrefsbot',
  'mj12bot',
  'dotbot',
  'petalbot',
  'chrome-lighthouse',
  'pagespeed',
  'gtmetrix',
];

// GitHub language colors
const LANGUAGE_COLORS = {
  JavaScript: '#f1e05a',
  TypeScript: '#3178c6',
  Python: '#3572A5',
  Java: '#b07219',
  HTML: '#e34c26',
  CSS: '#563d7c',
  Shell: '#89e051',
  Dockerfile: '#384d54',
  Go: '#00ADD8',
  Rust: '#dea584',
  PHP: '#4F5D95',
  Ruby: '#701516',
  C: '#555555',
  'C++': '#f34b7d',
  'C#': '#178600',
};

// Track if Swiper is loaded
let swiperLoaded = false;
let swiperLoading = false;

/**
 * Detect if current user agent is a known bot/crawler
 */
function isBot() {
  const ua = navigator.userAgent.toLowerCase();
  return BOT_PATTERNS.some(pattern => ua.includes(pattern));
}

/**
 * Load CSS dynamically
 */
function loadCSS(url) {
  return new Promise((resolve, reject) => {
    // Check if already loaded
    if (document.querySelector(`link[href="${url}"]`)) {
      resolve();
      return;
    }
    
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = url;
    link.onload = resolve;
    link.onerror = reject;
    document.head.appendChild(link);
  });
}

/**
 * Load JavaScript dynamically
 */
function loadScript(url) {
  return new Promise((resolve, reject) => {
    // Check if already loaded
    if (document.querySelector(`script[src="${url}"]`)) {
      resolve();
      return;
    }
    
    const script = document.createElement('script');
    script.src = url;
    script.onload = resolve;
    script.onerror = reject;
    document.body.appendChild(script);
  });
}

/**
 * Load Swiper CSS and JS
 */
async function loadSwiper() {
  if (swiperLoaded || swiperLoading) return;
  
  swiperLoading = true;
  console.log('[GitHub Repos] Loading Swiper...');
  
  try {
    // Load CSS and JS in parallel
    await Promise.all([
      loadCSS(SWIPER_CSS_URL),
      loadScript(SWIPER_JS_URL)
    ]);
    
    swiperLoaded = true;
    console.log('[GitHub Repos] Swiper loaded successfully');
  } catch (error) {
    console.error('[GitHub Repos] Failed to load Swiper:', error);
    throw error;
  } finally {
    swiperLoading = false;
  }
}

/**
 * Format relative time (e.g., "2 days ago")
 */
function formatRelativeTime(dateString) {
  const date = new Date(dateString);
  const now = new Date();
  const diffMs = now - date;
  const diffSec = Math.floor(diffMs / 1000);
  const diffMin = Math.floor(diffSec / 60);
  const diffHour = Math.floor(diffMin / 60);
  const diffDay = Math.floor(diffHour / 24);
  const diffMonth = Math.floor(diffDay / 30);
  const diffYear = Math.floor(diffDay / 365);

  if (diffSec < 60) return 'just now';
  if (diffMin < 60) return `${diffMin} minute${diffMin > 1 ? 's' : ''} ago`;
  if (diffHour < 24) return `${diffHour} hour${diffHour > 1 ? 's' : ''} ago`;
  if (diffDay < 30) return `${diffDay} day${diffDay > 1 ? 's' : ''} ago`;
  if (diffMonth < 12) return `${diffMonth} month${diffMonth > 1 ? 's' : ''} ago`;
  return `${diffYear} year${diffYear > 1 ? 's' : ''} ago`;
}

/**
 * Get cached data if valid
 */
function getCachedRepos() {
  try {
    const cached = localStorage.getItem(CACHE_KEY);
    if (!cached) return null;

    const { data, timestamp } = JSON.parse(cached);
    if (Date.now() - timestamp < CACHE_DURATION) {
      console.log('[GitHub Repos] Using cached data');
      return data;
    }
  } catch (error) {
    console.error('[GitHub Repos] Cache error:', error);
  }
  return null;
}

/**
 * Cache repositories data
 */
function cacheRepos(data) {
  try {
    localStorage.setItem(CACHE_KEY, JSON.stringify({
      data,
      timestamp: Date.now()
    }));
  } catch (error) {
    console.error('[GitHub Repos] Failed to cache:', error);
  }
}

/**
 * Shuffle array randomly (Fisher-Yates algorithm)
 */
function shuffleArray(array) {
  const shuffled = [...array];
  for (let i = shuffled.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
  }
  return shuffled;
}

/**
 * Fetch repositories from GitHub API
 */
async function fetchRepos() {
  // Check cache first
  const cached = getCachedRepos();
  if (cached) return shuffleArray(cached);

  // Fetch from API
  const url = `${GITHUB_API_URL}?sort=updated&direction=desc&per_page=${MAX_REPOS}`;
  const response = await fetch(url);

  if (!response.ok) {
    throw new Error(`GitHub API error: ${response.status}`);
  }

  // Log rate limit info
  console.log('[GitHub Repos] Rate Limit Remaining:', response.headers.get('X-RateLimit-Remaining'));

  const repos = await response.json();
  cacheRepos(repos);
  return shuffleArray(repos);
}

/**
 * Format file size
 */
function formatSize(sizeInKB) {
  if (!sizeInKB || sizeInKB === 0) return '0 KB';
  
  if (sizeInKB < 1024) {
    return `${sizeInKB} KB`;
  }
  
  const sizeInMB = (sizeInKB / 1024).toFixed(1);
  return `${sizeInMB} MB`;
}

/**
 * Truncate repository name if longer than 25 characters
 */
function truncateRepoName(name) {
  if (name.length > 25) {
    return name.substring(0, 40) + '...';
  }
  return name;
}

/**
 * Truncate description if longer than 110 characters
 */
function truncateDescription(description) {
  if (description.length > 110) {
    return description.substring(0, 110) + '...';
  }
  return description;
}

/**
 * Create repository card HTML
 */
function createRepoCard(repo) {
  const languageColor = LANGUAGE_COLORS[repo.language] || '#858585';
  const rawDescription = repo.description || 'No description available';
  const description = truncateDescription(rawDescription);
  const createdAt = formatRelativeTime(repo.created_at);
  const updatedAt = formatRelativeTime(repo.pushed_at || repo.updated_at);
  const displayName = truncateRepoName(repo.name);
  const repoSize = formatSize(repo.size);
  const license = repo.license ? repo.license.spdx_id || repo.license.name : null;
  const isArchived = repo.archived;

  return `
    <div class="swiper-slide">
      <article class="repo-card">
        <div class="repo-card-header">
          <h3 class="repo-name hero-subtitle">
            <a href="${repo.html_url}" target="_blank" rel="noopener noreferrer" title="${displayName}">
              ${displayName}
            </a>
          </h3>
        </div>
        
        <p class="repo-description">${description}</p>
        
        <div class="repo-meta">
          <div class="repo-meta-row">
            <span class="meta-item" title="Created">
              📅 Created ${createdAt}
            </span>
            <span class="meta-separator">•</span>
            <span class="meta-item" title="Last updated">
              🔄 Updated ${updatedAt}
            </span>
            ${repo.language ? `
              <span class="meta-separator">•</span>
              <span class="meta-item">
                <span class="language-dot" style="background-color: ${languageColor};"></span>
                ${repo.language}
              </span>
            ` : ''}
          </div>
          
          <div class="repo-meta-row">
            ${isArchived ? `
              <span class="meta-item archived" title="This repository is archived">
                📦 Archived
              </span>
              <span class="meta-separator">•</span>
            ` : ''}
            <span class="meta-item" title="Repository size">
              💾 ${repoSize}
            </span>
            ${license ? `
              <span class="meta-separator">•</span>
              <span class="meta-item" title="License">
                ⚖️ ${license}
              </span>
            ` : ''}
            ${!repo.language && !isArchived && !license ? `
              <span class="meta-item">
                −
              </span>
            ` : ''}
          </div>
        </div>
      </article>
    </div>
  `;
}

/**
 * Initialize Swiper slider
 */
function initSwiper() {
  // Check if Swiper is available
  if (typeof Swiper === 'undefined') {
    console.error('[GitHub Repos] Swiper is not loaded');
    return null;
  }

  // Desktop: 2 cards visible, Mobile: 1 card visible
  return new Swiper('.repos-swiper', {
    slidesPerView: 1,
    spaceBetween: 20,
    navigation: {
      nextEl: '.swiper-button-next',
      prevEl: '.swiper-button-prev',
    },
    pagination: {
      el: '.swiper-pagination',
      clickable: true,
    },
    breakpoints: {
      640: {
        slidesPerView: 1,
        spaceBetween: 20,
      },
      768: {
        slidesPerView: 1,
        spaceBetween: 20,
      },
      1024: {
        slidesPerView: 2,
        spaceBetween: 30,
      },
      1400: {
        slidesPerView: 2,
        spaceBetween: 40,
      },
    },
  });
}

/**
 * Render repositories
 */
function renderRepos(repos) {
  const container = document.getElementById('repos-container');
  const swiper = document.querySelector('.repos-swiper');
  const loading = document.getElementById('repos-loading');

  if (!container || !swiper || !loading) {
    console.error('[GitHub Repos] Required DOM elements not found');
    return;
  }

  // Generate HTML
  const html = repos.map(createRepoCard).join('');
  container.innerHTML = html;

  // Show container, hide loading
  loading.style.display = 'none';
  swiper.style.display = 'block';

  // Initialize Swiper
  initSwiper();
}

/**
 * Show error state
 */
function showError(error) {
  console.error('[GitHub Repos] Error:', error);

  const loading = document.getElementById('repos-loading');
  const errorDiv = document.getElementById('repos-error');

  if (loading) loading.style.display = 'none';
  if (errorDiv) errorDiv.style.display = 'block';
}

/**
 * Main initialization - loads Swiper first, then fetches repos
 */
async function initGitHubRepos() {
  // Skip for bots to avoid XHR errors in SEO tools
  if (isBot()) {
    console.log('[GitHub Repos] Bot detected, skipping initialization');
    const loading = document.getElementById('repos-loading');
    const section = document.querySelector('.github-repos');
    if (loading) loading.style.display = 'none';
    if (section) section.style.display = 'none';
    return;
  }

  try {
    console.log('[GitHub Repos] Initializing...');
    
    // Load Swiper first
    await loadSwiper();
    
    // Then fetch and render repos
    const repos = await fetchRepos();
    renderRepos(repos);
    
    console.log('[GitHub Repos] Successfully loaded', repos.length, 'repositories');
  } catch (error) {
    showError(error);
  }
}

/**
 * Setup Intersection Observer for lazy loading
 * Only initializes when GitHub section is visible in viewport
 */
function setupLazyLoading() {
  const section = document.querySelector('.github-repos');
  
  if (!section) {
    console.log('[GitHub Repos] Section not found, skipping lazy loading');
    return;
  }

  // Skip for bots
  if (isBot()) {
    const loading = document.getElementById('repos-loading');
    if (loading) loading.style.display = 'none';
    section.style.display = 'none';
    return;
  }

  // Create observer with generous margin (load before section is fully visible)
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          console.log('[GitHub Repos] Section visible, starting initialization...');
          observer.disconnect(); // Only trigger once
          initGitHubRepos();
        }
      });
    },
    {
      rootMargin: '200px', // Start loading 200px before section enters viewport
      threshold: 0
    }
  );

  observer.observe(section);
  console.log('[GitHub Repos] Lazy loading observer set up');
}

// Initialize lazy loading when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', setupLazyLoading);
} else {
  setupLazyLoading();
}
