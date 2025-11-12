# Migration: Bootstrap/PHP Stack → Next.js Static Export

<!-- Tech Stack -->
[![Next.js](https://img.shields.io/badge/Next.js-16.0-black?logo=next.js)](https://nextjs.org/)
[![Node.js](https://img.shields.io/badge/Node.js-20-339933?logo=node.js)](https://nodejs.org/)
[![TypeScript](https://img.shields.io/badge/TypeScript-5.9-3178C6?logo=typescript)](https://www.typescriptlang.org/)
[![React](https://img.shields.io/badge/React-18-61DAFB?logo=react)](https://reactjs.org/)
<!-- Infrastructure -->
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker)](https://www.docker.com/)
[![CI/CD](https://img.shields.io/badge/CI%2FCD-GitHub%20Actions-2088FF?logo=github-actions)](https://github.com/features/actions)
[![Deployment](https://img.shields.io/badge/Deploy-SSH-success?logo=githubactions&logoColor=white)](https://github.com/features/actions)
<!-- Security -->
## Security Scanning
[![Security](https://img.shields.io/badge/Security-Hardened-success?logo=github)](MIGRATION_SECURITY.md)
[![Secrets](https://img.shields.io/badge/Secrets-External-critical?logo=1password)](MIGRATION_SECURITY.md)
[![CodeQL](https://img.shields.io/badge/CodeQL-enabled-success?logo=github)](https://github.com/JoZapf/jozapf_de/security/code-scanning)
[![npm audit](https://img.shields.io/badge/npm%20audit-passing-success?logo=npm)](/)
[![Dependabot](https://img.shields.io/badge/Dependabot-enabled-success?logo=dependabot)](https://github.com/dependabot)
[![Snyk](https://img.shields.io/badge/Snyk-Enhanced%20vulnerability%20detection%20with%20reachability%20analysis-success?logo=snyk&logoColor=white)](https://github.com/features/actions)


---
Multi-layered security approach:
- **npm audit**: Baseline security scanning
- **Snyk**: Enhanced vulnerability detection with reachability analysis
- **Dependabot**: Automated dependency updates
- **CodeQL**: Static code analysis

---
<!-- Quality & Compliance -->
[![GDPR](https://img.shields.io/badge/GDPR-Compliant-success)](https://gdpr.eu/)
[![Status](https://img.shields.io/badge/Status-Production-success)](/)

---
<!-- Learning Context -->
[![Apprentice](https://img.shields.io/badge/AppDevelopper-In%20Training-informational?logo=education)](https://en.wikipedia.org/wiki/Vocational_education)
[![Documentation](https://img.shields.io/badge/Docs-Comprehensive-success?logo=readthedocs)](/)

---

> **Portfolio Website Migration Journey**  
> From containerized Bootstrap/PHP development to modern Next.js static export with automated CI/CD deployment to shared hosting.

---

## 📑 Table of Contents

- [Executive Summary](#executive-summary)
- [Why This Migration?](#why-this-migration)
- [Technical Stack Comparison](#technical-stack-comparison)
- [Architecture & Key Decisions](#architecture--key-decisions)
- [Migration Challenges & Solutions](#migration-challenges--solutions)
  - [Challenge 1: Paradigm Shift - Server-Side PHP → Client-Side React](#challenge-1-paradigm-shift---server-side-php--client-side-react)
  - [Challenge 2: SSG Export for Shared Hosting](#challenge-2-ssg-export-for-shared-hosting)
  - [Challenge 3: Secrets Management Across Environments](#challenge-3-secrets-management-across-environments)
  - [Challenge 4: CI/CD Pipeline Without SSH Access](#challenge-4-cicd-pipeline-without-ssh-access)
- [Deployment Workflow](#deployment-workflow)
- [Lessons Learned](#lessons-learned)
- [Getting Started](#getting-started)
- [Project Context](#project-context)

---

## Executive Summary

This document details the migration of **jozapf.de** from a containerized Bootstrap/PHP development environment to a modern **Next.js 16** static site generator (SSG) with TypeScript, automated versioning, and CI/CD deployment to Hetzner shared hosting.

### Migration Highlights

| Aspect | Achievement |
|--------|-------------|
| **Tech Stack** | Bootstrap/PHP → Next.js 16 + TypeScript + React 18 |
| **Development** | Docker Compose multi-stage builds with hot-reload |
| **Deployment** | GitHub Actions → FTPS to Hetzner (no SSH required) |
| **Versioning** | Automated Git tag + timestamp injection into `summary.json` |
| **Security** | GitHub Secrets for credentials, env-based configuration |
| **Export Mode** | Pure static HTML/CSS/JS - runs on any webspace |
| **Internationalization** | Bilingual DE/EN with dynamic routing and SEO |

### Learning Objectives

As an **IT specialist apprentice** (Application Development), this project demonstrates:

✅ Modern web development workflows  
✅ Container orchestration and multi-stage Docker builds  
✅ CI/CD automation and deployment patterns  
✅ TypeScript, React, and Next.js fundamentals  
✅ Secrets management and security-first practices  
✅ Git-based versioning and changelog maintenance  
✅ Documentation and knowledge transfer  
✅ Local **AI-assisted development** with Large Language Models (MCP)

### Development Methodology

**Transparency Notice:** This project was developed with **AI assistance** using Claude-Code via the Model Context Protocol (MCP).

**How AI was integrated into development:**
- **Local LLM setup**: Configured Claude-Desktop App (Windows) for working locally , installed Claude-Code on WSL
- **Architecture decisions**: Created through collaborative AI pair-programming sessions
- **Security implementations & Secrets managemnt**: Researched with AI assistance, then independently validated and tested
- **Learning acceleration**: Interactive problem-solving with LLMs to understand complex concepts faster
- **Code review**: AI-suggested patterns evaluated, tested, and often modified before implementation
- **Documentation**: AI helped structure and expand technical writing, all content human-verified

**What this is NOT:**
- ❌ Copy-paste development without understanding
- ❌ Bypassing proper learning fundamentals

**What this IS:**
- ✅ Modern software development practices
- ✅ Honest representation of the development process
- ✅ Demonstration of effective use of emerging technologies
- ✅ Proof of ability to critically evaluate and integrate AI suggestions

**Why document this openly?**
- Maintains **professional integrity** and realistic skill representation
- Shows understanding of when and how to leverage AI effectively
- Helps other apprentices understand realistic learning paths
- Demonstrates that using tools doesn't diminish the learning process - it's about **understanding** what you build

**Skills demonstrated:**
- Critical evaluation of AI-generated solutions
- Prompt engineering and effective LLM collaboration  
- Independent validation and security testing
- Integration of AI tools into professional workflows
- Knowing when to use AI and when to solve problems independently

---

## Why This Migration?

### Business Rationale

1. **Modern Tech Stack**: Next.js offers better performance, developer experience, and ecosystem support
2. **Static Export Compatibility**: Hetzner shared hosting doesn't support Node.js runtime - SSG solves this
3. **Automated Deployments**: Reduce manual FTPS uploads, eliminate human error
4. **Version Transparency**: Machine-readable `summary.json` for LLMs and automated tools
5. **Scalability**: Easy to extend with API routes, MDX, or external CMS integration

### Technical Advantages

| Feature | Bootstrap/PHP | Next.js SSG |
|---------|---------------|-------------|
| **Hot Reload** | Manual refresh | Built-in Fast Refresh |
| **Type Safety** | None | TypeScript throughout |
| **Build Process** | Manual | Automated, optimized |
| **SEO** | Manual meta tags | Built-in metadata API |
| **Deployment** | Manual FTPS | Automated CI/CD |
| **Versioning** | Manual updates | Auto-injected from Git |
| **Internationalization** | Manual duplicate pages | Route-based DE/EN with SEO |

---

## Technical Stack Comparison

### Before: Bootstrap/PHP Stack

```yaml
# Simplified Previous Architecture
services:
  nginx:
    image: nginx:1.27-alpine
    ports: ["8088:80"]
    volumes:
      - ./:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf

  php:
    build: ./docker/php
    env_file: ["${SECRETS_DIR}/app.env"]
    volumes:
      - ./:/var/www/html
```

**Stack**: Nginx + PHP-FPM + Bootstrap 5 + Vanilla JS  
**Development**: Docker containers with manual code edits  
**Deployment**: Manual FTPS upload  
**Secrets**: `.env` files bind-mounted from `SECRETS_DIR`

### After: Next.js Static Export

```yaml
# Current Architecture
services:
  next-dev:
    image: node:20-alpine
    working_dir: /app
    environment:
      NEXT_TELEMETRY_DISABLED: "1"
      CHOKIDAR_USEPOLLING: "true"  # Stable file watching on Windows/WSL
    volumes:
      - .:/app
      - next_node_modules:/app/node_modules
    command: npx next@16 dev -p 3000 -H 0.0.0.0
    ports: ["3000:3000"]

  next-static:
    image: nginx:1.27-alpine
    volumes:
      - ./out:/usr/share/nginx/html:ro
    ports: ["8080:80"]
    tmpfs: ["/var/cache/nginx", "/var/run"]
    read_only: true
```

**Stack**: Next.js 16 + TypeScript 5.9 + React 18  
**Development**: Docker with hot-reload, isolated node_modules in named volume  
**Deployment**: GitHub Actions → Automated FTPS  
**Secrets**: GitHub Actions Secrets (build-time env injection)

---

## Architecture & Key Decisions

### 0. Asset & Service Distribution Strategy

**Current Implementation:**
- Main domain: `jozapf.de` (Next.js static export)
- Assets subdomain: `assets.jozapf.de` (static media: images, fonts, icons)
- Static files cached with long expiry times via `.htaccess` rules
- Cookie-less domain for assets to reduce HTTP overhead

**Planned Architecture Extensions:**

```
jozapf.de           → Main website (Next.js SSG)
assets.jozapf.de    → Static media (images, fonts, SVGs) - CDN-ready
cdn.jozapf.de       → Content Delivery Network endpoint (future exploration)
api.jozapf.de       → API services (future - serverless/microservices)
```

**Benefits of this architecture:**
- ✅ **Domain sharding**: Parallel asset loading (HTTP/1.1 optimization)
- ✅ **Cookie-less domain**: Reduced request overhead for static assets
- ✅ **Cache control**: Independent cache policies per service
- ✅ **Security isolation**: API/assets separated from main application
- ✅ **Scalability**: Easy migration to CDN providers (Cloudflare, etc.)
- ✅ **CORS flexibility**: Fine-grained cross-origin resource control

#### Main Site `.htaccess` (jozapf.de)

```apache
# Selective redirect: Only media assets → assets.jozapf.de
# JS/CSS/PHP/HTML stay on main domain
RewriteCond %{HTTP_HOST} !^assets\.jozapf\.de$ [NC]
RewriteRule ^assets/(png|jpe?g|svg|ico|favicon|fonts)/(.*)$ https://assets.jozapf.de/$1/$2 [R=302,L]

# Security: Block sensitive files
RewriteRule ^assets/php/(dashboard-login\.php|dashboard\.php|contact-php-handler\.php)$ - [L]
RewriteRule ^assets/php/ - [F]

# Security Headers
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "SAMEORIGIN"
```

**Key implementation details:**
- `[R=302,L]` for testing phase (switch to 301 after verification)
- Only image/font paths redirected - preserves Next.js JS/CSS on main domain
- Prevents redirect loops with `RewriteCond %{HTTP_HOST}` check

#### Assets Subdomain `.htaccess` (assets.jozapf.de)

```apache
# CORS: Allow cross-origin requests from main domain
<FilesMatch "\.(woff2?|ttf|otf|eot|svg|png|jpe?g|ico)$">
  Header set Access-Control-Allow-Origin "https://jozapf.de"
  Header set Access-Control-Allow-Methods "GET, OPTIONS"
  Header set Access-Control-Allow-Headers "Accept, Origin, Content-Type"
</FilesMatch>

# Aggressive caching: 30 days for all static media
ExpiresByType image/png  "access plus 30 days"
ExpiresByType image/jpeg "access plus 30 days"
ExpiresByType image/svg+xml "access plus 30 days"
ExpiresByType font/woff2 "access plus 30 days"
```

**Why separate assets subdomain:**
- **Performance**: Browser can make more parallel requests to different domains
- **Security**: No cookies sent with asset requests (reduces bandwidth)
- **Caching**: Can set aggressive cache headers without affecting main site
- **Future-proof**: Easy to swap to a CDN provider later

#### Future CDN/API Strategy

**cdn.jozapf.de** (Planned):
- Potential Cloudflare Workers integration
- Edge caching for global distribution
- Image optimization/WebP conversion at edge
- Still learning: Difference between CDN and simple subdomain hosting

**api.jozapf.de** (Planned):
- Serverless functions (Vercel/Netlify/Cloudflare Workers)
- Separate authentication domain
- Microservices for contact form, analytics, etc.
- Still researching: When to use vs. traditional PHP backend

**Learning journey:**
- Understanding domain separation benefits (practical experience in progress)
- Exploring CDN concepts and edge computing (theoretical knowledge building)
- Researching microservices vs. monolithic architecture (evaluating trade-offs)
- Investigating serverless deployment options (not yet clear when to use)

### 1. Static Site Generation (SSG) Strategy

**Decision**: Use Next.js `output: "export"` mode  
**Rationale**: Hetzner shared hosting doesn't support Node.js runtime or SSR

```typescript
// next.config.ts
const nextConfig: NextConfig = {
  output: 'export',              // Static HTML export
  images: { unoptimized: true }, // No Image Optimization API needed
  trailingSlash: true,           // Apache/shared hosting compatibility
  reactStrictMode: true,
};
```

**Trade-offs**:
- ❌ No API routes, SSR, or ISR
- ✅ Zero server-side dependencies
- ✅ CDN-friendly, maximum portability
- ✅ Predictable build output

### 2. Docker Multi-Environment Setup

**Decision**: Separate dev and production-preview containers  
**Rationale**: Development needs hot-reload; production verification needs static serving

```yaml
# Development: Hot-reload in container
next-dev:
  profiles: ["next"]
  environment:
    CHOKIDAR_USEPOLLING: "true"   # Fix for Windows/WSL file watching
    WATCHPACK_POLLING: "true"
  volumes:
    - .:/app
    - next_node_modules:/app/node_modules  # Named volume prevents Windows conflicts

# Production Preview: Nginx serves /out directory
next-static:
  profiles: ["next"]
  volumes:
    - ./out:/usr/share/nginx/html:ro
  read_only: true
  tmpfs: ["/var/cache/nginx", "/var/run"]
```

**Key Learning**: Named volumes for `node_modules` prevent permission/sync issues on Windows/WSL

### 3. Bilingual Content Strategy (DE/EN)

**Decision**: Implement client-side dynamic `lang` attribute switching without breaking static generation  
**Rationale**: Support international audience while maintaining SSG compatibility and avoiding hydration mismatches

**Architecture**:
```typescript
// app/layout.tsx - Static-friendly approach
export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="de" data-bs-theme="dark" className="h-100" suppressHydrationWarning>
      <body>
        <LangAttribute />  {/* Client component updates lang dynamically */}
        {children}
      </body>
    </html>
  );
}

// app/components/LangAttribute.tsx - Client-side lang switcher
'use client';
import { usePathname } from 'next/navigation';
import { useEffect } from 'react';

export default function LangAttribute() {
  const pathname = usePathname();
  useEffect(() => {
    const locale = pathname?.startsWith('/en') ? 'en' : 'de';
    if (document.documentElement.lang !== locale) {
      document.documentElement.lang = locale;
    }
  }, [pathname]);
  return null;
}
```

**URL Structure**:
```
jozapf.de/           → German (default)
jozapf.de/en/        → English
jozapf.de/en/print/  → English print version
```

**SEO Implementation**:
```typescript
// Metadata with hreflang alternates
export const metadata: Metadata = {
  alternates: {
    canonical: "/",
    languages: {
      'de': "https://jozapf.de/",
      'en': "https://jozapf.de/en/",
      "x-default": "https://jozapf.de/",
    },
  },
};
```

**Key Challenge**: Initial approach using `headers()` or `cookies()` forced dynamic rendering, breaking static export:

```typescript
// ❌ This breaks static generation
export default async function RootLayout({ children }: { children: ReactNode }) {
  const headersList = await headers();  // Forces dynamic rendering!
  const pathname = headersList.get('x-invoke-path') || '/';
  const locale = pathname.startsWith('/en') ? 'en' : 'de';
  return <html lang={locale}>...</html>;
}
```

**Solution**: Use `suppressHydrationWarning` + client component for post-hydration updates:
- Server renders initial `lang="de"` for all pages
- Client component updates `document.documentElement.lang` after mount
- `suppressHydrationWarning` prevents React warnings
- All pages remain statically generated (compatible with `dynamic = "error"`)

**Benefits**:
- ✅ Full static generation preserved
- ✅ No hydration mismatches
- ✅ Proper SEO with hreflang tags
- ✅ Clean URL structure for both languages
- ✅ Accessible language switching UI
- ✅ Compatible with Next.js 16's strict static requirements

**Key Learnings**:
- 🎓 Server components can't use `headers()` or `cookies()` without forcing dynamic rendering
- 🎓 `suppressHydrationWarning` prevents console errors when `lang` updates client-side
- 🎓 Client components can safely update DOM attributes after hydration
- 🎓 Static export pages maintain `dynamic = "error"` compatibility

### 4. Single Source of Truth (SoT) for Versioning

**Decision**: Generate `summary.json` from Git metadata at build time  
**Rationale**: Ensure version/timestamp consistency across UI and machine-readable endpoints

```typescript
// scripts/generate-summary.ts
async function main() {
  const version = 
    process.env.GIT_TAG ??           // CI-injected tag
    process.env.npm_package_version ?? // package.json fallback
    "0.0.0-dev";
  
  const last_updated = 
    process.env.BUILD_DATE ??        // CI-injected timestamp
    new Date().toISOString();         // Local fallback

  const summary = {
    project: "jozapf.de",
    version,
    last_updated,
    key_points: [ /* ... */ ]
  };

  fs.writeFileSync("public/summary.json", JSON.stringify(summary, null, 2));
}
```

**Workflow Integration**:
```json
// package.json
{
  "scripts": {
    "prebuild": "tsx scripts/generate-summary.ts",
    "build": "next build",
    "postbuild": "next export"
  }
}
```

### 5. Fragment-Based Content Management

**Decision**: Keep HTML fragments separate, inject at build time  
**Rationale**: Preserve existing Bootstrap markup during migration, enable incremental refactoring

```typescript
// app/page.tsx
export default function Home() {
  const header = readFragment("header-fragment.html");
  const main   = readFragment("home-fragment.html");
  const footer = readFragment("footer-fragment.html");

  return (
    <>
      <div dangerouslySetInnerHTML={{ __html: header }} />
      <main dangerouslySetInnerHTML={{ __html: main }} />
      <div dangerouslySetInnerHTML={{ __html: footer }} />
    </>
  );
}
```

**Benefits**:
- ✅ Gradual migration path (not a big-bang rewrite)
- ✅ Reuse proven Bootstrap components
- ✅ Team members can edit HTML without React knowledge
- ⚠️ Less type-safe, requires careful XSS consideration

---

## Migration Challenges & Solutions

### Challenge 1: Paradigm Shift - Server-Side PHP → Client-Side React

**Problem**: Moving from PHP's template-driven model to React's component-based architecture required rethinking data flow, state management, and rendering strategies.

**Initial Approach** (Naive):
```typescript
// ❌ Tried to fetch data client-side
function Page() {
  const [data, setData] = useState(null);
  useEffect(() => {
    fetch('/api/data').then(r => r.json()).then(setData);
  }, []);
}
```

**Issue**: API routes don't exist in `output: "export"` mode → runtime errors

**Solution**: Build-time data loading with Node.js filesystem APIs

```typescript
// ✅ Read at build time (SSG)
export default function Home() {
  const fragments = {
    header: fs.readFileSync('app/header-fragment.html', 'utf8'),
    main: fs.readFileSync('app/home-fragment.html', 'utf8'),
    footer: fs.readFileSync('app/footer-fragment.html', 'utf8'),
  };
  
  return (
    <>
      <div dangerouslySetInnerHTML={{ __html: fragments.header }} />
      <main dangerouslySetInnerHTML={{ __html: fragments.main }} />
      <div dangerouslySetInnerHTML={{ __html: fragments.footer }} />
    </>
  );
}
```

**Key Learnings**:
- 🎓 Understand the difference between server components (build-time) and client components (runtime)
- 🎓 Static export = no server-side code execution after build
- 🎓 Use `fs` APIs in server components, never in `'use client'` components

---

### Challenge 2: SSG Export for Shared Hosting

**Problem**: Hetzner webspace provides FTPS access only - no Node.js runtime, no SSH, no PM2/systemd

**Research Phase**:
1. ❌ Considered Vercel/Netlify → Cost concerns, vendor lock-in
2. ❌ Attempted SSR with reverse proxy → Not possible on shared hosting
3. ✅ Discovered Next.js `output: "export"` mode → Perfect fit

**Implementation**:

```typescript
// next.config.ts
const nextConfig: NextConfig = {
  output: 'export',              // Generate static HTML
  trailingSlash: true,           // Apache ModRewrite compatibility
  images: { unoptimized: true }, // No server-side optimization
};
```

**Build Output Structure**:
```
out/
├── index.html
├── changelog/
│   └── index.html
├── print/
│   └── index.html
├── _next/
│   └── static/...
├── assets/
│   └── ...
└── summary.json
```

**Deployment**:
```bash
# Local test of static output
npm run build
npx serve out -l 8080

# Or with Docker
docker compose --profile next up next-static
```

**Key Learnings**:
- 🎓 SSG removes the need for Node.js runtime completely
- 🎓 `trailingSlash: true` prevents redirect loops on Apache
- 🎓 Always test the exported `out/` directory locally before deploying

---

### Challenge 3: Secrets Management Across Environments

**Problem**: Credentials needed in three contexts:
1. **Local development** (Docker Compose)
2. **CI/CD pipeline** (GitHub Actions)
3. **Build-time injection** (version info, API keys)

**Anti-Pattern** (What NOT to do):
```yaml
# ❌ NEVER commit secrets to Git
services:
  php:
    environment:
      - DB_PASSWORD=supersecret123
      - SMTP_PASSWORD=hunter2
```

**Solution**: Three-layer secrets architecture

#### Layer 1: Local Development (Docker Compose)

```yaml
# compose.yml
services:
  php:
    env_file:
      - "${SECRETS_DIR}/app.env"  # External, gitignored directory
    volumes:
      - type: bind
        source: "${SECRETS_DIR}/app.env"
        target: /var/www/html/assets/php/app.env
        read_only: true
```

```bash
# .env (Repository - NOT secrets!)
PROJECT_SLUG=jozapf-de
PROJECT_ROOT_WINDOWS=E:/Projects/jozapf-de
SECRETS_DIR=E:/Secrets/jozapf-de  # Points to external location
HTTP_PORT=8088
```

```bash
# E:/Secrets/jozapf-de/app.env (NEVER in Git!)
SMTP_HOST=smtp.example.com
SMTP_USER=noreply@jozapf.de
SMTP_PASSWORD=actual_password_here
DB_PASSWORD=database_password
```

#### Layer 2: CI/CD (GitHub Secrets)

```yaml
# .github/workflows/deploy.yml
jobs:
  build:
    steps:
      - name: Set build metadata
        run: |
          echo "GIT_TAG=$(git describe --tags --abbrev=0 || echo '')" >> $GITHUB_ENV
          echo "BUILD_DATE=$(date -u +'%Y-%m-%dT%H:%M:%SZ')" >> $GITHUB_ENV

      - name: Build (SSG Export)
        env:
          GIT_TAG: ${{ env.GIT_TAG }}
          BUILD_DATE: ${{ env.BUILD_DATE }}
        run: npm run build

      - name: Deploy via FTPS
        uses: SamKirkland/FTP-Deploy-Action@v4
        with:
          server: ${{ secrets.FTP_SERVER }}
          username: ${{ secrets.FTP_USERNAME }}
          password: ${{ secrets.FTP_PASSWORD }}
          protocol: ftps
          local-dir: ./out/
          server-dir: ${{ secrets.FTP_DIR }}
```

**GitHub Secrets Setup**:
```
Repository → Settings → Secrets and variables → Actions → New repository secret

FTP_SERVER      → ftp.jozapf.de
FTP_USERNAME    → deploy-user
FTP_PASSWORD    → [secure password]
FTP_DIR         → /public_html/
```

#### Layer 3: Build-Time Environment Variables

```typescript
// scripts/generate-summary.ts
const version = 
  process.env.GIT_TAG?.replace(/^v/, '') ||  // CI-injected
  getPkgVersion();                            // Fallback

const lastUpdated = 
  process.env.BUILD_DATE ||                   // CI-injected
  getGitIsoDate();                            // Fallback
```

**Key Learnings**:
- 🎓 Never store secrets in `.env` files inside the repository
- 🎓 Use `env_file` for Docker, GitHub Secrets for CI/CD
- 🎓 Document the required secrets clearly (see [Getting Started](#getting-started))
- 🎓 Test builds locally WITHOUT secrets to ensure graceful degradation

---

### Challenge 4: CI/CD Pipeline Without SSH Access

**Problem**: Traditional deployment (SSH + rsync/SCP) not available on Hetzner shared hosting

**Deployment Options Evaluated**:

| Method | Available | Performance | Atomicity | Verdict |
|--------|-----------|-------------|-----------|---------|
| SSH + rsync | ❌ No | Excellent | Partial | Not possible |
| FTP (plain) | ✅ Yes | Good | ❌ No | Insecure |
| FTPS (TLS) | ✅ Yes | Good | ❌ No | ✅ Chosen |
| SFTP | ❌ No | Excellent | ❌ No | Not available |

**Implementation**: GitHub Actions + FTPS

```yaml
# .github/workflows/deploy.yml
name: Build and Deploy to Hetzner

on:
  push:
    branches: [main]
  workflow_dispatch:

jobs:
  build-deploy:
    runs-on: ubuntu-latest
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v4
        with:
          fetch-depth: 0  # Needed for git describe --tags

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: 20
          cache: npm

      - name: Install dependencies
        run: npm ci

      - name: Set build metadata
        run: |
          echo "GIT_TAG=$(git describe --tags --abbrev=0 2>/dev/null || echo '')" >> $GITHUB_ENV
          echo "BUILD_DATE=$(date -u +'%Y-%m-%dT%H:%M:%SZ')" >> $GITHUB_ENV

      - name: Build static site
        env:
          GIT_TAG: ${{ env.GIT_TAG }}
          BUILD_DATE: ${{ env.BUILD_DATE }}
        run: |
          npm run build
          ls -lah out/

      - name: Deploy to Hetzner via FTPS
        uses: SamKirkland/FTP-Deploy-Action@v4.3.5
        with:
          server: ${{ secrets.FTP_SERVER }}
          username: ${{ secrets.FTP_USERNAME }}
          password: ${{ secrets.FTP_PASSWORD }}
          protocol: ftps
          port: 21
          local-dir: ./out/
          server-dir: ${{ secrets.FTP_DIR }}
          dangerous-clean-slate: false  # Preserve existing files not in out/
          exclude: |
            **/.git*
            **/.DS_Store
            **/node_modules/**
```

**Deployment Flow Diagram**:
```
┌─────────────┐
│ Local Dev   │
│ (git push)  │
└──────┬──────┘
       │
       ▼
┌─────────────────────────────────────────────────────────────┐
│ GitHub Actions Runner (ubuntu-latest)                       │
│                                                              │
│  1. Checkout code (with tags)                               │
│  2. Setup Node.js 20 (with npm cache)                       │
│  3. npm ci                                                   │
│  4. Inject GIT_TAG + BUILD_DATE                             │
│  5. npm run build (generates /out with summary.json)        │
│  6. FTPS Upload to Hetzner                                  │
│     - Incremental transfer (only changed files)             │
│     - Atomic on per-file basis                              │
└──────┬──────────────────────────────────────────────────────┘
       │
       ▼
┌─────────────────────┐
│ Hetzner Webspace    │
│ /public_html/       │
│   ├── index.html    │
│   ├── _next/        │
│   ├── assets/       │
│   └── summary.json  │
└─────────────────────┘
```

## Deployment Workflow

### Complete Local → Production Flow

```
┌─────────────────────┐
│ Local Development   │
│ - Docker Compose    │
│ - Hot Reload        │
│ - localhost:3000    │
└──────────┬──────────┘
           │ git commit
           │ git push
           ▼
┌─────────────────────┐
│ GitHub Repository   │
│ - Source Code       │
│ - Git Tags          │
│ - Secrets (Actions) │
└──────────┬──────────┘
           │ Trigger on push
           ▼
┌─────────────────────────────────────┐
│ GitHub Actions CI/CD                │
│ 1. Checkout (with tags)             │
│ 2. Setup Node.js 20                 │
│ 3. npm ci                           │
│ 4. Inject GIT_TAG + BUILD_DATE      │
│ 5. npm run build → /out             │
│ 6. FTPS upload to Hetzner           │
└──────────┬──────────────────────────┘
           │ Deploy
           ▼
┌─────────────────────┐
│ Hetzner Webspace    │
│ - Static HTML/CSS/JS│
│ - summary.json      │
│ - Live: jozapf.de   │
└─────────────────────┘
```

### Step-by-Step Process

#### 1. Local Development
```bash
# Start development environment
docker compose --profile next up next-dev

# Make changes, test locally (http://localhost:3000)
# Commit changes
git add .
git commit -m "feat: add new timeline component"

# Optional: bump version and tag
npm run version:minor  # Creates v2.1.0 tag + commit
```

#### 2. Production Preview (Local)
```bash
# Build and preview exactly what will be deployed
npm run build

# Test static output with local Nginx
docker compose --profile next up next-static
# Visit http://localhost:8080

# Or use serve
npx serve out -l 8080
```

#### 3. Deploy to GitHub
```bash
# Push code + tags
git push origin main
git push --tags

# GitHub Actions automatically:
# - Detects push to main
# - Runs build workflow
# - Deploys to Hetzner via FTPS
```

#### 4. Verify Deployment
```bash
# Check summary.json version
curl https://jozapf.de/summary.json | jq .

# Check GitHub Actions status
gh run list --workflow=deploy.yml
```

### Versioning Strategy

**Semantic Versioning** via `npm run version:{patch|minor|major}`:

```bash
# Current: v2.0.2
npm run version:patch  # → v2.0.3 (bugfix)
npm run version:minor  # → v2.1.0 (new feature)
npm run version:major  # → v3.0.0 (breaking change)
```

**What happens**:
1. Updates `package.json` version
2. Creates Git commit: `chore: bump version to X.Y.Z`
3. Creates Git tag: `vX.Y.Z`
4. Prompts to push: `git push && git push --tags`
5. GitHub Actions picks up tag → injects into `summary.json`

---

## Getting Started

### Prerequisites

- Node.js 20+ (for local development)
- Docker + Docker Compose (optional, but recommended)
- Git with SSH keys configured
- Hetzner webspace (or similar shared hosting with FTPS)

### Local Development Setup

1. **Clone the repository**
   ```bash
   git clone git@github.com:JoZapf/jozapf_de.git
   cd jozapf_de
   ```

2. **Install dependencies**
   ```bash
   npm ci
   ```

3. **Start development server**
   
   **Option A: Without Docker (simple)**
   ```bash
   npm run dev
   # Visit http://localhost:3000
   ```

   **Option B: With Docker (recommended)**
   ```bash
   # Start Next.js dev server
   docker compose --profile next up next-dev
   # Visit http://localhost:3000
   ```

4. **Build and preview**
   ```bash
   npm run build
   docker compose --profile next up next-static
   # Visit http://localhost:8080
   ```

### Required Secrets (for CI/CD)

Add these in **GitHub → Settings → Secrets and variables → Actions**:

| Secret Name | Description | Example |
|-------------|-------------|---------|
| `FTP_SERVER` | Hetzner FTP hostname | `ftp.your-domain.de` |
| `FTP_USERNAME` | FTP username | `u12345678` |
| `FTP_PASSWORD` | FTP password | `***` |
| `FTP_DIR` | Target directory on server | `/public_html/` or `/` |

### First Deployment

1. **Create a version tag**
   ```bash
   npm run version:minor  # Creates v2.1.0
   git push origin main
   git push --tags
   ```

2. **GitHub Actions will automatically**:
   - Build the static site
   - Inject version and timestamp
   - Deploy to Hetzner via FTPS

3. **Verify deployment**
   ```bash
   curl https://your-domain.de/summary.json
   ```

---

## Project Context

### Timeline

- **Initial Setup (2024)**: Bootstrap 5 + PHP + Docker development environment
- **Security Hardening (Oct 2024)**: CSRF protection, HMAC auth, automated log anonymization
- **Migration Phase (Nov 2024)**: Transition to Next.js 16 + TypeScript
- **Current Status**: Production-ready, automated deployments

### Educational Context

This project serves as a **practical learning platform** during my apprenticeship as an **IT Specialist for Application Development** (Fachinformatiker für Anwendungsentwicklung) in Germany. Key learning goals:

- Modern web development workflows
- Container orchestration and DevOps practices
- Security-first development (secrets management, GDPR compliance)
- CI/CD automation and deployment strategies
- Technical documentation and knowledge transfer

### Related Projects

- **Contact Form Abuse Prevention**: PHP-based contact form with GDPR compliance, CSRF protection, and automated log anonymization

---

## Contact

**Jo Zapf**  
Web- & Application Development, Cross-Media Solutions  
Berlin, Germany

- Website: [jozapf.de](https://jozapf.de)
- GitHub: [@JoZapf](https://github.com/JoZapf)
- LinkedIn: [Jo Zapf](https://www.linkedin.com/in/jo-zapf/)

---

**⭐ If you find this migration journey helpful, please consider starring this repository!**

*Last Updated: 2024-11-10 | Version: 2.0.3*
