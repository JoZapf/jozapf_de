// app/layout.tsx
import type { Metadata, Viewport } from "next";
import type { ReactNode } from "react";
import Script from "next/script";
import HeaderInfo from "@/components/HeaderInfo";
import "./globals.css";

/**
 * Head/SEO:
 * - Canonical/hreflang
 * - robots, keywords (OHNE themeColor hier)
 * - OpenGraph/Twitter
 * - Favicons/Manifest
 * - JSON-LD: WebSite + Person + ProfessionalService
 */
export const metadata: Metadata = {
  metadataBase: new URL("https://jozapf.de"),
  title:
    "Jo Zapf - Web Development, Application Development & Cross-Media Solutions | Berlin",
  description:
    "Jo Zapf offers professional web development, application development and cross-media solutions. He actually is specializing in Python, Java, JavaScript, Docker, CI/CD and secure cloud and deployment workflows & infrastructure.",
  keywords: [
    "Web Development",
    "Application Development",
    "Cross Media",
    "Containerized Services",
    "CI/CD",
    "Python",
    "Java",
    "JavaScript",
    "Linux",
    "DevOps",
    "Zero-Trust-Architecture",
    "Berlin",
  ],
  alternates: {
    canonical: "/",
    languages: {
      en: "https://jozapf.de/",
      "x-default": "https://jozapf.de/",
      "en-001": "https://jozapf.com/",
    },
  },
  robots: {
    index: true,
    follow: true,
    googleBot:
      "index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1",
  },

  manifest: "/assets/favicon/site.webmanifest",
  icons: {
    icon: [
      { url: "/assets/favicon/favicon-96x96.png", type: "image/png", sizes: "96x96" },
      { url: "/assets/favicon/favicon.svg", type: "image/svg+xml" },
    ],
    shortcut: ["/assets/favicon/favicon.ico"],
    apple: [{ url: "/assets/favicon/apple-touch-icon.png", sizes: "180x180", type: "image/png" }],
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
    title:
      "Jo Zapf - Web Development, Application Development & Cross-Media Solutions",
    description:
      "Professional digital solutions: Application Development, Web Development, DevOps, Docker, CI/CD and secure cloud infrastructure from Berlin.",
    images: [
      {
        url: "https://jozapf.de/assets/png/JoZapf_500x500.png",
        width: 500,
        height: 500,
        alt: "Jo Zapf - Concept, DevOps, Digital Solutions",
      },
    ],
    locale: "en_DE",
  },
  twitter: {
    card: "summary",
    title: "Jo Zapf - Web Development & Digital Solutions",
    description:
      "Application Development, Web Development, DevOps and Cross-Media Solutions from Berlin.",
    images: ["https://jozapf.de/assets/png/JoZapf_500x500.png"],
  },
};

// → Hier gehört themeColor hin (nicht in metadata)
export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  themeColor: "#212529",
};

// JSON-LD: WebSite (mit SearchAction)
const websiteSchema = {
  "@context": "https://schema.org",
  "@type": "WebSite",
  "@id": "https://jozapf.de/#website",
  url: "https://jozapf.de",
  name: "Jo Zapf - Digital Solutions",
  inLanguage: "de",
  potentialAction: {
    "@type": "SearchAction",
    target: "https://jozapf.de/?q={search_term_string}",
    "query-input": "required name=search_term_string",
  },
} as const;

// JSON-LD: Person
const personSchema = {
  "@context": "https://schema.org",
  "@type": "Person",
  "@id": "https://jozapf.de/#person",
  name: "Jo Zapf",
  url: "https://jozapf.de",
  image: "https://jozapf.de/assets/png/JoZapf_500x500.png",
  jobTitle:
    "IT specialist for application development in training & Web Developer, Cross-Media Artist & Multimedia-Designer",
  description:
    "Application Development, Web Development, DevOps, Cross-Media Solutions, Zero-Trust-Architecture, Secured Networks",
  address: { "@type": "PostalAddress", addressLocality: "Berlin", addressCountry: "DE" },
  sameAs: ["https://www.linkedin.com/in/jo-zapf/", "https://github.com/JoZapf"],
  knowsAbout: [
    "Web Development",
    "Application Development",
    "Network",
    "Cyber Security",
    "Python",
    "Java",
    "JavaScript",
    "Docker",
    "Github",
    "CI/CD",
    "DevOps",
    "Zero-Trust-Architecture",
    "Deployment Workflows",
    "Linux Administration",
    "Cloud Infrastructure",
  ],
} as const;

// JSON-LD: ProfessionalService
const serviceSchema = {
  "@context": "https://schema.org",
  "@type": "ProfessionalService",
  name: "Jo Zapf - Digital Solutions",
  description:
    "Professional Web Development, Application Development and Cross-Media Solutions",
  url: "https://jozapf.de",
  address: {
    "@type": "PostalAddress",
    addressLocality: "Berlin",
    addressCountry: "DE",
  },
  priceRange: "€€",
} as const;

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="de" data-bs-theme="dark" className="h-100">
      <head>
        {/* DNS Prefetch */}
        <link rel="dns-prefetch" href="https://www.linkedin.com" />
        <link rel="dns-prefetch" href="https://github.com" />

        {/* Preload kritischer Ressourcen */}
        <link rel="preload" href="/assets/css/bootstrap.css" as="style" />
        <link rel="preload" href="/assets/js/bootstrap.bundle.min.js" as="script" />
        <link rel="preload" href="/assets/png/JoZapf_500x500.png" as="image" />

        {/* Lokale Styles */}
        <link href="/assets/css/bootstrap.css" rel="stylesheet" />
        <link href="/assets/css/fonts.css" rel="stylesheet" />
        <link href="/assets/css/cover.css" rel="stylesheet" />
        <link href="/assets/css/timeline.css" rel="stylesheet" />
        <link href="/assets/css/vertical_timeline.css" rel="stylesheet" />
        <link href="/assets/css/contact-form.css" rel="stylesheet" />
      </head>

      <body className="d-flex flex-column min-vh-100 text-bg-dark">
        <HeaderInfo />
        {children}

        {/* JS */}
        <Script src="/assets/js/bootstrap.bundle.min.js" strategy="afterInteractive" />
        <Script src="/assets/js/experience.js" strategy="afterInteractive" />

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

        {/* Strukturierte Daten */}
        <Script
          id="schema-website"
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(websiteSchema) }}
        />
        <Script
          id="schema-person"
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(personSchema) }}
        />
        <Script
          id="schema-professional-service"
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(serviceSchema) }}
        />
      </body>
    </html>
  );
}
