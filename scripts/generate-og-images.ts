/**
 * generate-og-images.ts
 * 
 * Build-time OG-Image-Generierung für jozapf.de
 * Erzeugt zweisprachige OG-Bilder (DE/EN) mit Glossy-Effekt
 * 
 * Verwendung: npx tsx scripts/generate-og-images.ts
 * Wird automatisch im prebuild ausgeführt
 * 
 * Output: assets-deploy/og/og-home-de.png, og-home-en.png
 * Deploy-Ziel: https://assets.jozapf.de/og/
 * 
 * @see docs/20251210_dynamic_og_runbook.md
 */

import satori from 'satori';
import { Resvg } from '@resvg/resvg-js';
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

// ESM __dirname equivalent
const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const PROJECT_ROOT = join(__dirname, '..');

// ============================================================================
// CONFIGURATION
// ============================================================================

const OG_WIDTH = 1200;
const OG_HEIGHT = 630;

const OUTPUT_DIR = join(PROJECT_ROOT, 'assets-deploy', 'og');

// Font paths (relative to project root)
const FONT_BOLD = join(PROJECT_ROOT, 'assets-deploy', 'fonts', 'Montserrat-Bold.ttf');
const FONT_REGULAR = join(PROJECT_ROOT, 'assets-deploy', 'fonts', 'Montserrat-Regular.ttf');
const FONT_MEDIUM = join(PROJECT_ROOT, 'assets-deploy', 'fonts', 'Montserrat-Medium.ttf');

// Background images
const BG_IMAGE_HOME = join(PROJECT_ROOT, 'assets-deploy', 'jpg', 'og_image_v2_1200x630_jozapf_de.jpg');
const BG_IMAGE_PRAKTIKUM = join(PROJECT_ROOT, 'assets-deploy', 'jpg', 'og_praktikum_1200x630.jpg');

// Content for each language
const CONTENT = {
  de: {
    title: 'www.jozapf.de',
    subtitle: 'Webentwicklung & Anwendungsentwicklung',
    description: 'Digitale Lösungen aus Berlin: Webentwicklung, Anwendungsentwicklung, Cross-Media-Design und sichere Cloud-Infrastruktur.',
    badge: 'https://jozapf.de',
    filename: 'og-home-de.png',
  },
  en: {
    title: 'www.jozapf.de',
    subtitle: 'Web Development & Application Development',
    description: 'Digital solutions from Berlin: Web development, application development, cross-media design and secure cloud infrastructure.',
    badge: 'https://jozapf.de',
    filename: 'og-home-en.png',
  },
  'praktikum-de': {
    title: 'www.jozapf.de',
    subtitle: 'Pflichtpraktikum Anwendungsentwicklung · Berlin 2026',
    description: '· 960 Stunden · Berlin · FIAE (IHK) · Abschlussprojekt · DevOps · CI/CD · Anwendungsentwicklung · Systemintegration · Zero-Trust ·',
    badge: 'https://jozapf.de/pflichtpraktikum-anwendungsentwicklung-berlin',
    filename: 'og-praktikum-de.png',
  },
  'praktikum-en': {
    title: 'www.jozapf.de',
    subtitle: 'Mandatory Internship · Application Development · Berlin 2026',
    description: '960 hours · Berlin · FIAE (IHK) · final project · DevOps · CI/CD · application development · system integration · zero-trust ·',
    badge: 'https://jozapf.de/en/pflichtpraktikum-anwendungsentwicklung-berlin',
    filename: 'og-praktikum-en.png',
  },
} as const;

// ============================================================================
// HELPER: Load image as Base64 Data URL
// ============================================================================

function loadImageAsBase64(imagePath: string): string {
  const imageBuffer = readFileSync(imagePath);
  const base64 = imageBuffer.toString('base64');
  
  // Determine MIME type from extension
  const ext = imagePath.toLowerCase().split('.').pop();
  const mimeTypes: Record<string, string> = {
    jpg: 'image/jpeg',
    jpeg: 'image/jpeg',
    png: 'image/png',
    webp: 'image/webp',
    gif: 'image/gif',
  };
  const mimeType = mimeTypes[ext || 'jpg'] || 'image/jpeg';
  
  return `data:${mimeType};base64,${base64}`;
}

// ============================================================================
// OG IMAGE TEMPLATE (JSX-like structure for Satori)
// ============================================================================
//
// Visual layer stack (bottom to top):
//
//   1. Background Image    – full-bleed photo, covers entire 1200×630 canvas
//   2. Dark Overlay         – semi-transparent gradient, dims the photo for text contrast
//   3. Glassmorphism Card   – near-fullsize frosted card (1190×620), holds all text
//   4. Glossy Overlay       – subtle top-half shine on the card (faux light reflection)
//   5. Content elements     – badge, title, subtitle, divider, description
//
// Color system:
//   rgba(r,g,b, ALPHA) – alpha controls opacity: 0.0 = invisible, 1.0 = fully opaque
//   #ffffff = white, used at varying alpha levels for the glass effect
//
// Satori constraint: no CSS classes, no shorthand – every style must be an inline object.
// ============================================================================

interface OGContentProps {
  title: string;
  subtitle: string;
  description: string;
  badge: string;
}

function createOGTemplate(content: OGContentProps, bgImageDataUrl: string) {
  return {
    type: 'div',
    props: {
      // ROOT CONTAINER – the full 1200×630 canvas
      style: {
        width: '100%',
        height: '100%',
        display: 'flex',
        alignItems: 'center',       // vertical center
        justifyContent: 'center',   // horizontal center
        position: 'relative',       // anchor for absolute children
        fontFamily: 'Montserrat',
        overflow: 'hidden',         // clip anything outside canvas
      },
      children: [

        // ── LAYER 1: BACKGROUND IMAGE ──────────────────────────────────
        // Fills entire canvas. objectFit:'cover' crops to fit (no distortion).
        // Which image is used depends on bgMap in main() – home vs praktikum.
        {
          type: 'img',
          props: {
            src: bgImageDataUrl,
            style: {
              position: 'absolute',
              top: 0,
              left: 0,
              width: '100%',
              height: '100%',
              objectFit: 'cover',
            },
          },
        },

        // ── LAYER 2: DARK OVERLAY ──────────────────────────────────────
        // Semi-transparent gradient over the photo. Makes white text readable.
        // Direction: 135deg = top-left → bottom-right diagonal.
        //
        // Color stops:
        //   rgba(26,26,46, 0.75)  – dark navy,    75% opaque (top-left, lightest)
        //   rgba(22,33,62, 0.85)  – dark blue,    85% opaque (center)
        //   rgba(15,52,96, 0.85)  – deeper blue,  85% opaque (bottom-right, darkest)
        //
        // ↑ Increase alpha values → darker overlay → more contrast, less photo visible.
        // ↓ Decrease alpha values → lighter overlay → more photo visible, less contrast.
        {
          type: 'div',
          props: {
            style: {
              position: 'absolute',
              top: 0,
              left: 0,
              right: 0,
              bottom: 0,
              background: 'linear-gradient(135deg, rgba(19, 19, 34, 0.85) 50%, rgba(0, 0, 0, 0.25) 100%, rgba(15,52,96,0.85) 100%)',
            },
          },
        },

        // ── LAYER 3: GLASSMORPHISM CARD ────────────────────────────────
        // A frosted-glass card that sits centered on the canvas.
        // Nearly fullsize (1190×620 inside 1200×630) – 5px visible edge on each side.
        {
          type: 'div',
          props: {
            style: {
              width: 1190,                // card width in px
              height: 620,                // card height in px
              borderRadius: 12,           // rounded corners
              padding: '48px 56px',       // inner spacing (top/bottom, left/right)
              display: 'flex',
              flexDirection: 'column',    // stack children vertically
              alignItems: 'center',       // center children horizontally
              justifyContent: 'center',   // center children vertically
              position: 'relative',       // anchor for glossy overlay + badge

              // Card fill: white at 7.5% → 1% opacity = barely visible frosted tint
              // 145deg = top-left to bottom-right, slightly steeper than the dark overlay
              background: 'linear-gradient(145deg, rgba(255,255,255,0.035) 0%, rgba(255,255,255,0.01) 100%)',

              // Card border: white at 18% opacity = faint edge line
              border: '1px solid rgba(255,255,255,0.18)',

              // Two shadows stacked:
              //   1) Deep drop shadow: black 50% opacity, 25px blur, pushed 50px down
              //   2) Thin inner glow: white 10% opacity, 1px solid ring
              boxShadow: '0 25px 50px -12px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.1)',
            },
            children: [

              // ── LAYER 4: GLOSSY OVERLAY ──────────────────────────────
              // Covers the top 50% of the card. Simulates light hitting a glass surface.
              // Gradient: white at 5% opacity at top → 2% at 35% height → fully transparent.
              // Increase 0.05/0.02 for a shinier look, decrease for subtler effect.
              {
                type: 'div',
                props: {
                  style: {
                    position: 'absolute',
                    top: 0,
                    left: 0,
                    right: 0,
                    height: '25%',                // covers top half of card
                    borderRadius: '12px 12px 0 0', // match card's top corners
                    background: 'linear-gradient(180deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 35%, transparent 100%)',
                  },
                },
              },

              // ── BADGE ────────────────────────────────────────────────
              // Small pill label pinned to top of card. Shows domain/URL.
              // White bg at 15% opacity, border at 25% opacity.
              // Text: white at 90% opacity, Montserrat Medium (500).
              {
                type: 'div',
                props: {
                  style: {
                    position: 'absolute',
                    top: 28,               // 28px from card top
                    display: 'flex',
                    padding: '10px 28px',  // vertical, horizontal padding
                    borderRadius: 12,      // rounded pill shape
                    background: 'rgba(255,255,255,0.15)',   // 15% white fill
                    border: '1px solid rgba(255,255,255,0.25)', // 25% white edge
                    fontSize: 20,
                    fontWeight: 500,       // Medium weight
                    color: 'rgba(255,255,255,0.9)',  // 90% white text
                    letterSpacing: '0.5px',
                  },
                  children: content.badge,
                },
              },

              // ── TITLE ────────────────────────────────────────────────
              // Main headline. Largest text on the image.
              // 72px, Bold (700), fully opaque white.
              // letterSpacing: -1px tightens character spacing for a modern look.
              {
                type: 'div',
                props: {
                  style: {
                    marginTop: 32,
                    fontSize: 72,          // ~30 chars max before overflow
                    fontWeight: 700,       // Bold
                    color: '#ffffff',       // 100% white – highest visual weight
                    textAlign: 'center',
                    lineHeight: 1.1,       // tight line spacing
                    letterSpacing: '-1px', // slightly condensed
                  },
                  children: content.title,
                },
              },

              // ── SUBTITLE ─────────────────────────────────────────────
              // Secondary text below title.
              // 32px, Medium (500), white at 85% opacity = slightly dimmer than title.
              {
                type: 'div',
                props: {
                  style: {
                    marginTop: 8,
                    fontSize: 32,          // ~50 chars max before overflow
                    fontWeight: 500,       // Medium
                    color: 'rgba(255,255,255,0.85)', // 85% white – visual hierarchy below title
                    textAlign: 'center',
                    lineHeight: 1.3,
                  },
                  children: content.subtitle,
                },
              },

              // ── DIVIDER ──────────────────────────────────────────────
              // Thin horizontal accent line between subtitle and description.
              // 120px wide, 3px tall. Gradient: orange → green → blue (brand colors).
              //   #e26b34 = warm orange (left)
              //   #336851 = muted green  (center)
              //   #1b3c65 = deep blue    (right)
              {
                type: 'div',
                props: {
                  style: {
                    marginTop: 32,
                    width: 120,
                    height: 3,
                    borderRadius: 2,
                    background: 'linear-gradient(90deg, #e26b34 0%, #336851 50%, #1b3c65 100%)',
                  },
                },
              },

              // ── DESCRIPTION ──────────────────────────────────────────
              // Longer text below divider. Wraps at 900px max width (~2 lines).
              // 24px, Regular (400), white at 70% opacity = clearly subordinate to title/subtitle.
              // ~150 chars max before it starts to look crowded.
              {
                type: 'div',
                props: {
                  style: {
                    marginTop: 28,
                    maxWidth: 900,         // text wrap boundary
                    fontSize: 24,
                    fontWeight: 400,       // Regular
                    color: 'rgba(255,255,255,0.7)', // 70% white – lowest in text hierarchy
                    textAlign: 'center',
                    lineHeight: 1.5,       // comfortable reading spacing
                  },
                  children: content.description,
                },
              },
            ],
          },
        },
      ],
    },
  };
}

// ============================================================================
// GENERATION LOGIC
// ============================================================================

async function generateOGImage(key: keyof typeof CONTENT, bgImageDataUrl: string): Promise<void> {
  const content = CONTENT[key];
  console.log(`  → Generating ${content.filename}...`);

  // Load fonts
  const fontBold = readFileSync(FONT_BOLD);
  const fontRegular = readFileSync(FONT_REGULAR);
  const fontMedium = readFileSync(FONT_MEDIUM);

  // Generate SVG with Satori
  const svg = await satori(createOGTemplate(content, bgImageDataUrl) as React.ReactNode, {
    width: OG_WIDTH,
    height: OG_HEIGHT,
    fonts: [
      {
        name: 'Montserrat',
        data: fontBold,
        weight: 700,
        style: 'normal',
      },
      {
        name: 'Montserrat',
        data: fontMedium,
        weight: 500,
        style: 'normal',
      },
      {
        name: 'Montserrat',
        data: fontRegular,
        weight: 400,
        style: 'normal',
      },
    ],
  });

  // Convert SVG to PNG with Resvg
  const resvg = new Resvg(svg, {
    fitTo: {
      mode: 'width',
      value: OG_WIDTH,
    },
  });
  const pngData = resvg.render();
  const pngBuffer = pngData.asPng();

  // Write to file
  const outputPath = join(OUTPUT_DIR, content.filename);
  writeFileSync(outputPath, pngBuffer);
  console.log(`    ✓ Saved: ${outputPath}`);
}

async function main(): Promise<void> {
  console.log('\n🖼️  OG-Image Generator for jozapf.de');
  console.log('═'.repeat(50));

  // Verify fonts exist
  const fonts = [FONT_BOLD, FONT_REGULAR, FONT_MEDIUM];
  for (const fontPath of fonts) {
    if (!existsSync(fontPath)) {
      console.error(`❌ Font not found: ${fontPath}`);
      process.exit(1);
    }
  }
  console.log('✓ Fonts verified');

  // Verify background images exist
  for (const bgPath of [BG_IMAGE_HOME, BG_IMAGE_PRAKTIKUM]) {
    if (!existsSync(bgPath)) {
      console.error(`❌ Background image not found: ${bgPath}`);
      process.exit(1);
    }
  }
  console.log('✓ Background images verified');

  // Load background images as Base64
  console.log('  Loading background images...');
  const bgHome = loadImageAsBase64(BG_IMAGE_HOME);
  const bgPraktikum = loadImageAsBase64(BG_IMAGE_PRAKTIKUM);
  console.log('  ✓ Loaded');

  // Map keys to background images
  const bgMap: Record<keyof typeof CONTENT, string> = {
    de: bgHome,
    en: bgHome,
    'praktikum-de': bgPraktikum,
    'praktikum-en': bgPraktikum,
  };

  // Ensure output directory exists
  if (!existsSync(OUTPUT_DIR)) {
    mkdirSync(OUTPUT_DIR, { recursive: true });
  }
  console.log(`✓ Output directory: ${OUTPUT_DIR}`);

  // Generate images
  console.log('\nGenerating OG images...');
  
  try {
    for (const key of Object.keys(CONTENT) as (keyof typeof CONTENT)[]) {
      await generateOGImage(key, bgMap[key]);
    }
    
    console.log('\n' + '═'.repeat(50));
    console.log('✅ OG images generated successfully!');
    console.log(`   Deploy target: https://assets.jozapf.de/og/`);
    console.log('');
  } catch (error) {
    console.error('\n❌ Error generating OG images:', error);
    process.exit(1);
  }
}

main();
