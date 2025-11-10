// app/changelog/page.tsx
import type { Metadata } from "next";
import fs from "node:fs";
import path from "node:path";

export const metadata: Metadata = {
  title: "Changelog | Jo Zapf",
  description: "Version history and updates for jozapf.de",
  alternates: {
    canonical: "/changelog/",
  },
};

export const dynamic = "force-static";

interface ChangelogEntry {
  version: string;
  date: string;
  changes: {
    type: "added" | "changed" | "fixed" | "removed" | "security";
    description: string;
  }[];
}

// Changelog data - manually maintained or could be auto-generated from Git tags
const changelog: ChangelogEntry[] = [
  {
    version: "2.1.0",
    date: "2024-11-09",
    changes: [
      {
        type: "added",
        description: "Bilingual support (German/English) with language toggle",
      },
      {
        type: "added",
        description: "Print-friendly versions of portfolio pages",
      },
      {
        type: "changed",
        description: "Improved SEO with hreflang tags for both languages",
      },
      {
        type: "security",
        description: "Repository history cleanup - removed sensitive backup files",
      },
    ],
  },
  {
    version: "2.0.2",
    date: "2024-11-08",
    changes: [
      {
        type: "added",
        description: "Asset distribution via assets.jozapf.de subdomain",
      },
      {
        type: "changed",
        description: "Optimized .htaccess for CORS and caching",
      },
      {
        type: "fixed",
        description: "Language selector styling on mobile devices",
      },
    ],
  },
  {
    version: "2.0.0",
    date: "2024-11-01",
    changes: [
      {
        type: "added",
        description: "Complete migration to Next.js 16 with SSG export",
      },
      {
        type: "added",
        description: "Automated CI/CD deployment via GitHub Actions",
      },
      {
        type: "added",
        description: "Automated versioning from Git tags",
      },
      {
        type: "changed",
        description: "Migrated from Bootstrap/PHP to Next.js + TypeScript",
      },
      {
        type: "removed",
        description: "Deprecated PHP development environment",
      },
    ],
  },
  {
    version: "1.5.0",
    date: "2024-10-15",
    changes: [
      {
        type: "added",
        description: "Contact form with GDPR compliance and abuse prevention",
      },
      {
        type: "security",
        description: "CSRF protection with HMAC authentication",
      },
      {
        type: "security",
        description: "Automated log anonymization (IP redaction)",
      },
    ],
  },
  {
    version: "1.0.0",
    date: "2024-08-01",
    changes: [
      {
        type: "added",
        description: "Initial portfolio website with Bootstrap 5",
      },
      {
        type: "added",
        description: "Docker development environment",
      },
      {
        type: "added",
        description: "Responsive timeline component",
      },
    ],
  },
];

const typeColors = {
  added: "success",
  changed: "primary",
  fixed: "warning",
  removed: "danger",
  security: "danger",
} as const;

const typeLabels = {
  added: "Added",
  changed: "Changed",
  fixed: "Fixed",
  removed: "Removed",
  security: "Security",
} as const;

export default function Changelog() {
  return (
    <>
      {/* Simplified Header for Changelog */}
      <header data-bs-theme="dark">
        <nav className="navbar navbar-dark bg-transparent sticky-top border-bottom border-secondary-subtle">
          <div className="container-xxl d-flex justify-content-between align-items-center">
            <a href="/" className="btn btn-outline-light btn-sm">
              ← Back to Home
            </a>
            <a href="/en/" className="btn btn-outline-light btn-sm">
              EN 🇬🇧
            </a>
          </div>
        </nav>
      </header>

      <main className="container-xxl py-5">
        <div className="row">
          <div className="col-12 col-lg-10 mx-auto">
            {/* Header */}
            <div className="mb-5">
              <h1 className="display-4 mb-3">Changelog</h1>
              <p className="lead text-secondary">
                All notable changes to this project are documented here.
              </p>
              <p className="text-secondary">
                This project follows{" "}
                <a
                  href="https://semver.org/"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="link-light"
                >
                  Semantic Versioning
                </a>
                .
              </p>
            </div>

            {/* Changelog Entries */}
            {changelog.map((entry, index) => (
              <div key={entry.version} className="mb-5">
                <div className="d-flex align-items-center mb-3">
                  <h2 className="h3 mb-0 me-3">
                    <a
                      href={`#v${entry.version}`}
                      id={`v${entry.version}`}
                      className="link-light text-decoration-none"
                    >
                      v{entry.version}
                    </a>
                  </h2>
                  <time className="text-secondary" dateTime={entry.date}>
                    {new Date(entry.date).toLocaleDateString("en-US", {
                      year: "numeric",
                      month: "long",
                      day: "numeric",
                    })}
                  </time>
                </div>

                <div className="list-group list-group-flush">
                  {entry.changes.map((change, changeIndex) => (
                    <div
                      key={changeIndex}
                      className="list-group-item bg-transparent border-secondary"
                    >
                      <div className="d-flex gap-2 align-items-start">
                        <span
                          className={`badge bg-${typeColors[change.type]} text-uppercase`}
                          style={{ minWidth: "80px" }}
                        >
                          {typeLabels[change.type]}
                        </span>
                        <span className="text-light">{change.description}</span>
                      </div>
                    </div>
                  ))}
                </div>

                {/* Divider except for last entry */}
                {index < changelog.length - 1 && (
                  <hr className="my-5 border-secondary opacity-25" />
                )}
              </div>
            ))}

            {/* Footer Info */}
            <div className="mt-5 pt-4 border-top border-secondary">
              <p className="text-secondary small">
                <strong>Note:</strong> This changelog is manually maintained.
                For the complete commit history, visit{" "}
                <a
                  href="https://github.com/JoZapf/jozapf_de"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="link-light"
                >
                  GitHub
                </a>
                .
              </p>
            </div>
          </div>
        </div>
      </main>

      <footer className="mt-auto border-top border-secondary-subtle">
        <div className="container-xxl py-3 small text-center text-secondary">
          <span>
            © 1999–{new Date().getFullYear()} | Jo Zapf | DE-Berlin
          </span>
        </div>
      </footer>
    </>
  );
}
