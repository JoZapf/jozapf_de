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
    title: 'Jo Zapf',
    subtitle: 'Webentwicklung & Anwendungsentwicklung',
    description: 'Digitale Lösungen aus Berlin: Webentwicklung, Anwendungsentwicklung, Cross-Media-Design und sichere Cloud-Infrastruktur.',
    badge: 'jozapf.de',
    filename: 'og-home-de.png',
  },
  en: {
    title: 'Jo Zapf',
    subtitle: 'Web Development & Application Development',
    description: 'Digital solutions from Berlin: Web development, application development, cross-media design and secure cloud infrastructure.',
    badge: 'jozapf.de',
    filename: 'og-home-en.png',
  },
  'praktikum-de': {
    title: 'Jo Zapf',
    subtitle: 'Pflichtpraktikum Anwendungsentwicklung',
    description: '· 960 Stunden · Berlin · FIAE (IHK) · Abschlussprojekt · DevOps · CI/CD · Anwendungsentwicklung · Systemintegration · Zero-Trust ·',
    badge: 'jozapf.de/pflichtpraktikum-anwendungsentwicklung-berlin',
    filename: 'og-praktikum-de.png',
  },
  'praktikum-en': {
    title: 'Jo Zapf',
    subtitle: 'Mandatory Internship · Application Development',
    description: '960 hours · Berlin · FIAE (IHK) · final project · DevOps · CI/CD · application development ·system integration · zero-trust ·',
    badge: 'jozapf.de/en/pflichtpraktikum-anwendungsentwicklung-berlin',
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

interface OGContentProps {
  title: string;
  subtitle: string;
  description: string;
  badge: string;
}

function createOGTemplate(content: OGContentProps, bgImageDataUrl: string) {
  // Satori uses React-like JSX but returns plain objects
  // All styles must be inline objects, no CSS classes
  return {
    type: 'div',
    props: {
      style: {
        width: '100%',
        height: '100%',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        position: 'relative',
        fontFamily: 'Montserrat',
        overflow: 'hidden',
      },
      children: [
        // Background Image (the abstract shapes motif)
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
        // Dark overlay for better text contrast
        {
          type: 'div',
          props: {
            style: {
              position: 'absolute',
              top: 0,
              left: 0,
              right: 0,
              bottom: 0,
              background: 'linear-gradient(135deg, rgba(26,26,46,0.85) 0%, rgba(22,33,62,0.80) 50%, rgba(15,52,96,0.75) 100%)',
            },
          },
        },
        // Main Card
        {
          type: 'div',
          props: {
            style: {
              width: 1100,
              height: 550,
              borderRadius: 32,
              padding: '48px 56px',
              display: 'flex',
              flexDirection: 'column',
              alignItems: 'center',
              justifyContent: 'center',
              position: 'relative',
              // Glass-morphism card
              background: 'linear-gradient(145deg, rgba(255,255,255,0.12) 0%, rgba(255,255,255,0.05) 100%)',
              border: '1px solid rgba(255,255,255,0.18)',
              boxShadow: '0 25px 50px -12px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.1)',
            },
            children: [
              // Glossy overlay (top shine)
              {
                type: 'div',
                props: {
                  style: {
                    position: 'absolute',
                    top: 0,
                    left: 0,
                    right: 0,
                    height: '50%',
                    borderRadius: '32px 32px 0 0',
                    background: 'linear-gradient(180deg, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0.02) 60%, transparent 100%)',
                  },
                },
              },
              // Badge
              {
                type: 'div',
                props: {
                  style: {
                    position: 'absolute',
                    top: 28,
                    display: 'flex',
                    padding: '10px 28px',
                    borderRadius: 50,
                    background: 'rgba(255,255,255,0.15)',
                    border: '1px solid rgba(255,255,255,0.25)',
                    fontSize: 20,
                    fontWeight: 500,
                    color: 'rgba(255,255,255,0.9)',
                    letterSpacing: '0.5px',
                  },
                  children: content.badge,
                },
              },
              // Title
              {
                type: 'div',
                props: {
                  style: {
                    marginTop: 32,
                    fontSize: 72,
                    fontWeight: 700,
                    color: '#ffffff',
                    textAlign: 'center',
                    lineHeight: 1.1,
                    letterSpacing: '-1px',
                  },
                  children: content.title,
                },
              },
              // Subtitle
              {
                type: 'div',
                props: {
                  style: {
                    marginTop: 8,
                    fontSize: 32,
                    fontWeight: 500,
                    color: 'rgba(255,255,255,0.85)',
                    textAlign: 'center',
                    lineHeight: 1.3,
                  },
                  children: content.subtitle,
                },
              },
              // Divider line
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
              // Description
              {
                type: 'div',
                props: {
                  style: {
                    marginTop: 28,
                    maxWidth: 900,
                    fontSize: 24,
                    fontWeight: 400,
                    color: 'rgba(255,255,255,0.7)',
                    textAlign: 'center',
                    lineHeight: 1.5,
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
