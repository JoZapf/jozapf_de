// app/layout.tsx
import type { Metadata, Viewport } from "next";
import type { ReactNode } from "react";
import Script from "next/script";
import HeaderInfo from "@/components/HeaderInfo";
import LangAttribute from "@/components/LangAttribute";
import "./globals.css";

/**
 * Head/SEO:
 * - Canonical/hreflang
 * - robots, keywords
 * - OpenGraph/Twitter
 * - Favicons/Manifest
 * - JSON-LD: WebSite + Person + ProfessionalService (verknüpft via @id)
 * 
 * Optimiert: 2025-12-03
 * - Sprache: Komplett Deutsch auf Root (/)
 * - og:locale: de_DE
 * - JSON-LD: Vollständige ImageObjects, Schema-Verknüpfungen
 * - Strategie: Kombination A+C (Werdegang als Stärke)
 */
export const metadata: Metadata = {
  metadataBase: new URL("https://jozapf.de"),
  title: "Jo Zapf – Webentwicklung & Anwendungsentwicklung | Berlin",
  description:
    "Webentwicklung und Anwendungsentwicklung aus Berlin. " +
    "Über 25 Jahre Erfahrung in Cross-Media-Design, Fotografie und IT. " +
    "Spezialisiert auf Python, Java, Docker, CI/CD und sichere Cloud-Infrastruktur.",
  keywords: [
    "Webentwicklung",
    "Anwendungsentwicklung",
    "Fachinformatiker",
    "Cross-Media",
    "Fotografie",
    "Python",
    "Java",
    "Docker",
    "DevOps",
    "CI/CD",
    "Berlin",
  ],
  alternates: {
    canonical: "/",
    languages: {
      'de': "https://jozapf.de/",
      'en': "https://jozapf.de/en/",
      "x-default": "https://jozapf.de/",
    },
  },
  robots: {
    index: true,
    follow: true,
    googleBot:
      "index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1",
  },

  manifest: "https://assets.jozapf.de/favicon/site.webmanifest",
  icons: {
    icon: [
      { url: "https://assets.jozapf.de/favicon/favicon-96x96.png", type: "image/png", sizes: "96x96" },
      { url: "https://assets.jozapf.de/favicon/favicon.svg", type: "image/svg+xml" },
    ],
    shortcut: ["https://assets.jozapf.de/favicon/favicon.ico"],
    apple: [{ url: "https://assets.jozapf.de/favicon/apple-touch-icon.png", sizes: "180x180", type: "image/png" }],
  },

  appleWebApp: {
    capable: true,
    statusBarStyle: "black-translucent",
    title: "Jo Zapf",
  },

  openGraph: {
    type: "website",
    siteName: "Jo Zapf",
    url: "https://jozapf.de/",
    title: "Jo Zapf – Webentwicklung & Anwendungsentwicklung",
    description:
      "Digitale Lösungen aus Berlin: Webentwicklung, Anwendungsentwicklung, " +
      "Cross-Media-Design und sichere Cloud-Infrastruktur.",
    locale: "de_DE",
    images: [
      {
        url: "https://assets.jozapf.de/jpg/og_image_v2_1200x630_jozapf_de.jpg",
        secureUrl: "https://assets.jozapf.de/jpg/og_image_v2_1200x630_jozapf_de.jpg",
        width: 1200,
        height: 630,
        alt: "Jo Zapf – Webentwicklung und Anwendungsentwicklung aus Berlin",
        type: "image/jpeg",
      },
    ],
  },
  twitter: {
    card: "summary_large_image",
    title: "Jo Zapf – Webentwicklung & Digitale Lösungen",
    description:
      "Webentwicklung, Anwendungsentwicklung und Cross-Media-Lösungen aus Berlin.",
    images: ["https://assets.jozapf.de/jpg/og_image_v2_1200x630_jozapf_de.jpg"],
  },
};

// → Hier gehört themeColor hin (nicht in metadata)
export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  themeColor: "#212529",
};

// JSON-LD: WebSite (verknüpft mit Person via author)
const websiteSchema = {
  "@context": "https://schema.org",
  "@type": "WebSite",
  "@id": "https://jozapf.de/#website",
  url: "https://jozapf.de",
  name: "Jo Zapf – Digitale Lösungen",
  description: "Portfolio und Dienstleistungen für Webentwicklung, Anwendungsentwicklung und Cross-Media-Design",
  inLanguage: "de",
  author: { "@id": "https://jozapf.de/#person" },
} as const;

// JSON-LD: Person (Strategie A: Werdegang als Stärke)
const personSchema = {
  "@context": "https://schema.org",
  "@type": "Person",
  "@id": "https://jozapf.de/#person",
  name: "Jo Zapf",
  url: "https://jozapf.de",
  image: {
    "@type": "ImageObject",
    "@id": "https://jozapf.de/#personimage",
    url: "https://assets.jozapf.de/jpg/JoZapf_500x500.jpg",
    contentUrl: "https://assets.jozapf.de/jpg/JoZapf_500x500.jpg",
    width: 500,
    height: 500,
    caption: "Jo Zapf – Webentwickler und Fachinformatiker aus Berlin",
    inLanguage: "de",
  },
  jobTitle: "Fachinformatiker für Anwendungsentwicklung",
  description:
    "Webentwickler und angehender Fachinformatiker mit über 25 Jahren Erfahrung " +
    "in Cross-Media-Design, Fotografie und IT-Administration. " +
    "Spezialisiert auf Full-Stack-Entwicklung, DevOps und sichere Cloud-Infrastruktur.",
  address: {
    "@type": "PostalAddress",
    addressLocality: "Berlin",
    addressRegion: "Berlin",
    addressCountry: "DE",
  },
  sameAs: [
    "https://www.linkedin.com/in/jo-zapf/",
    "https://github.com/JoZapf",
  ],
  knowsAbout: [
    // Entwicklung
    "Webentwicklung",
    "Anwendungsentwicklung",
    "Python",
    "Java",
    "JavaScript",
    "Next.js",
    "Node.js",
    // DevOps & Infrastruktur
    "Docker",
    "CI/CD",
    "GitHub Actions",
    "DevOps",
    "Linux-Administration",
    "Cloud-Infrastruktur",
    "Zero-Trust-Architektur",
    // Kreativ
    "Cross-Media-Design",
    "Fotografie",
    "Videoproduktion",
    "Grafikdesign",
    "Motion Graphics",
    // IT & Netzwerk
    "Netzwerkadministration",
    "IT-Sicherheit",
  ],
  worksFor: { "@id": "https://jozapf.de/#service" },
} as const;

// JSON-LD: ProfessionalService (Strategie C: Dienstleistungen vollständig)
const serviceSchema = {
  "@context": "https://schema.org",
  "@type": "ProfessionalService",
  "@id": "https://jozapf.de/#service",
  name: "Jo Zapf – Digitale Lösungen",
  description:
    "Professionelle Webentwicklung, Anwendungsentwicklung und Cross-Media-Lösungen " +
    "aus Berlin. Von der Konzeption bis zur sicheren Produktion.",
  url: "https://jozapf.de",
  logo: {
    "@type": "ImageObject",
    url: "https://assets.jozapf.de/jpg/JoZapf_500x500.jpg",
    width: 500,
    height: 500,
  },
  image: {
    "@type": "ImageObject",
    url: "https://assets.jozapf.de/jpg/og_image_v2_1200x630_jozapf_de.jpg",
    contentUrl: "https://assets.jozapf.de/jpg/og_image_v2_1200x630_jozapf_de.jpg",
    width: 1200,
    height: 630,
    caption: "Jo Zapf – Digitale Lösungen aus Berlin",
    inLanguage: "de",
  },
  founder: { "@id": "https://jozapf.de/#person" },
  address: {
    "@type": "PostalAddress",
    addressLocality: "Berlin",
    addressRegion: "Berlin",
    addressCountry: "DE",
  },
  areaServed: {
    "@type": "Country",
    name: "Deutschland",
  },
  serviceType: [
    "Webentwicklung",
    "Anwendungsentwicklung",
    "Cross-Media-Design",
    "Fotografie",
    "Videoproduktion",
    "IT-Beratung",
  ],
} as const;

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="de" data-bs-theme="dark" className="h-100" suppressHydrationWarning>
      <head>
        {/* DNS Prefetch */}
        <link rel="dns-prefetch" href="https://www.linkedin.com" />
        <link rel="dns-prefetch" href="https://github.com" />

        {/* Preload kritischer Ressourcen */}
        <link rel="preload" href="/assets/css/bootstrap.min.css" as="style" />
        <link rel="preload" href="/assets/js/bootstrap.bundle.min.js" as="script" />

        {/* Lokale Styles */}
        <link href="/assets/css/variables.css" rel="stylesheet" />
        <link href="/assets/css/bootstrap.min.css" rel="stylesheet" />
        <link href="/assets/css/fonts.css" rel="stylesheet" />
        <link href="/assets/css/breakpoints.css" rel="stylesheet" />
        <link href="/assets/css/cover.css" rel="stylesheet" />
        <link href="/assets/css/timeline.css" rel="stylesheet" />
        <link href="/assets/css/vertical_timeline.css" rel="stylesheet" />
        <link href="/assets/css/github_repos.css" rel="stylesheet" />
        <link href="/assets/css/contact-form.css" rel="stylesheet" />
        <link href="/assets/css/lang-toggle.css" rel="stylesheet" />
        
        {/* Swiper.js CSS - Lazy loaded by github-repos.js */}

        {/* Strukturierte Daten (JSON-LD) - Native script tags für Crawler-Kompatibilität */}
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(websiteSchema) }}
        />
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(personSchema) }}
        />
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(serviceSchema) }}
        />
      </head>

      <body className="d-flex flex-column min-vh-100 text-bg-dark">
        <LangAttribute />
        <HeaderInfo />
        {children}

        {/* JS */}
        {/* GitHub Repositories Display (loads Swiper lazily via Intersection Observer) */}
        <Script src="/assets/js/github-repos.js" strategy="afterInteractive" />
        
        <Script src="/assets/js/bootstrap.bundle.min.js" strategy="afterInteractive" />
        <Script src="/assets/js/experience.js" strategy="afterInteractive" />
        <Script src="/assets/js/lang-detect.js" strategy="afterInteractive" />

        {/* Fallbacks (Jahre/©) */}
        <Script id="boot-fallback" strategy="afterInteractive">
          {`(function(){
            var yc = document.getElementById('years-count');
            var hasYears = yc && yc.textContent && yc.textContent.trim() !== '' && yc.textContent !== '0';
            if (!hasYears) {
                var start = new Date('1999-01-01'), now = new Date();
                var y = now.getFullYear() - start.getFullYear();
                var m = now.getMonth() - start.getMonth();
                var d = now.getDate() - start.getDate();
                if (m < 0 || (m === 0 && d < 0)) y--;
                if (yc) yc.textContent = String(y);
                var c = document.getElementById('copyright');
                if (c) { var Y = new Date().getFullYear(); c.innerHTML = "&copy; 1999–"+Y+" | Jo Zapf | DE-Berlin"; }
            }
          })();`}
        </Script>

        {/* Kontaktformular Lazy-Loader */}
        <Script id="contact-loader" type="module" strategy="afterInteractive">
          {`const CONFIG = {
            formURL: '/assets/html/contact-form-wrapper.html',
            logicURL: '/assets/js/contact-form-logic.js',
            triggers: '#menuPanel a[href*="contact"], a[href="#contact"], a[data-action="contact"]'
          };

          async function loadContactForm(shouldScroll = false) {
            const mount = document.querySelector('.contact_form');
            if (!mount) {
              console.error('Contact form mount point not found');
              return;
            }

            // Add ID for anchor jumping
            if (!mount.id) {
              mount.id = 'contact';
            }

            if (mount.getAttribute('data-loaded') === 'true') {
              if (shouldScroll) {
                mount.scrollIntoView({ behavior: 'smooth', block: 'start' });
              }
              return;
            }

            try {
              const res = await fetch(CONFIG.formURL, { headers: { 'X-Requested-With': 'fetch' } });
              if (!res.ok) throw new Error('HTTP ' + res.status);
              
              const html = await res.text();
              mount.innerHTML = html;
              mount.setAttribute('data-loaded', 'true');

              // Import and initialize the contact form logic
              let mod = null;
              try {
                mod = await import(CONFIG.logicURL);
              } catch (e) {
                console.error('Failed to import contact-form-logic.js:', e);
              }

              // Try different initialization methods
              let initialized = false;
              
              if (mod && typeof mod.initContactForm === 'function') {
                try {
                  mod.initContactForm(mount);
                  initialized = true;
                } catch (e) {
                  console.error('initContactForm failed:', e);
                }
              }
              
              if (!initialized && mod && typeof mod.default === 'function') {
                try {
                  mod.default(mount);
                  initialized = true;
                } catch (e) {
                  console.error('default export failed:', e);
                }
              }
              
              if (!initialized && typeof window !== 'undefined' && typeof window.initContactForm === 'function') {
                try {
                  window.initContactForm(mount);
                  initialized = true;
                } catch (e) {
                  console.error('window.initContactForm failed:', e);
                }
              }

              if (!initialized) {
                console.warn('Contact form loaded but not initialized - no valid init function found');
              }

              mount.dispatchEvent(new CustomEvent('contact:ready', { bubbles: true }));

              // Scroll to form if requested
              if (shouldScroll) {
                setTimeout(() => {
                  mount.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 100);
              }

            } catch (e) {
              console.error('Contact form failed to load:', e);
            }
          }

          // Handle click events on contact links
          document.addEventListener('click', (ev) => {
            const target = ev.target instanceof Element ? ev.target : null;
            const link = target && target.closest(CONFIG.triggers);
            if (link) {
              ev.preventDefault();
              loadContactForm(true);
            }
          });

          // Check for hash on page load
          if (location.hash && (location.hash === '#contact' || location.hash === '#contact-form-anchor')) {
            loadContactForm(true);
          }`}
        </Script>

      </body>
    </html>
  );
}
