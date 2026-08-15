#!/usr/bin/env node
/**
 * scripts/generate-tabler-icons-svg-css.js
 *
 * Generates webpack/generated/tabler-icons-svg.css — a CSS file that renders
 * Tabler icons via SVG mask-image instead of the webfont @font-face mechanism.
 *
 * This eliminates the cached-font / CSS↔woff2 version-skew that caused
 * certain `ti-*` glyphs to render as blank squares in Tabler 7.6.x
 * (issue #9479). All existing `<i class="ti ti-NAME">` markup is preserved
 * unchanged — only the rendering backend changes.
 *
 * How it works:
 *   1. Scans src/ and webpack/ for literal `ti-NAME` class tokens.
 *   2. Resolves each to an SVG from @tabler/icons (outline or filled).
 *   3. Emits a .ti base rule (mask sizing / currentColor fill) plus one
 *      .ti-NAME { mask-image: url("data:image/svg+xml,...") } rule per icon.
 *
 * Run automatically via npm `prebuild:webpack` / `prebuild:js` hooks.
 * Do NOT run this script from inside the webpack/generated/ directory.
 *
 * Usage:  node scripts/generate-tabler-icons-svg-css.js
 */

'use strict';

const fs = require('fs');
const path = require('path');

// ─── Paths ────────────────────────────────────────────────────────────────────
const ROOT = path.resolve(__dirname, '..');
const ICONS_OUTLINE = path.join(ROOT, 'node_modules/@tabler/icons/icons/outline');
const ICONS_FILLED = path.join(ROOT, 'node_modules/@tabler/icons/icons/filled');
const SCAN_DIRS = [
  path.join(ROOT, 'src'),
  path.join(ROOT, 'webpack'),
];
const SCAN_EXTENSIONS = new Set(['.php', '.js', '.ts', '.tsx', '.twig', '.html', '.css', '.scss']);
const OUT_DIR = path.join(ROOT, 'webpack', 'generated');
const OUT_FILE = path.join(OUT_DIR, 'tabler-icons-svg.css');

// ─── Step 1: Scan source files for used ti-* icon names ───────────────────────

/**
 * Recursively collect all files with relevant extensions from a directory.
 * Skips node_modules, src/skin/v2 (compiled output), src/skin/external, src/vendor.
 */
function collectFiles(dir, files = []) {
  const SKIP_DIRS = new Set(['node_modules', 'v2', 'external', 'vendor', 'propel', 'generated']);
  let entries;
  try {
    entries = fs.readdirSync(dir, { withFileTypes: true });
  } catch (_e) {
    return files;
  }
  for (const entry of entries) {
    if (entry.isDirectory()) {
      if (SKIP_DIRS.has(entry.name)) continue;
      collectFiles(path.join(dir, entry.name), files);
    } else if (entry.isFile() && SCAN_EXTENSIONS.has(path.extname(entry.name))) {
      files.push(path.join(dir, entry.name));
    }
  }
  return files;
}

// Matches: `ti-alpha-num-ericname` tokens (icon name segment after the prefix)
const TI_TOKEN_RE = /\bti-([a-z0-9]+(?:-[a-z0-9]+)*)\b/g;

const usedNames = new Set();

for (const dir of SCAN_DIRS) {
  for (const file of collectFiles(dir)) {
    let content;
    try {
      content = fs.readFileSync(file, 'utf8');
    } catch (_e) {
      continue;
    }
    for (const m of content.matchAll(TI_TOKEN_RE)) {
      usedNames.add(m[1]); // the part after "ti-"
    }
  }
}

// ─── Step 2: Resolve each name to an SVG file ─────────────────────────────────

/**
 * Minify an SVG string: collapse whitespace between tags, trim, and remove
 * XML declaration. Keeps attribute values unchanged.
 */
function minifySvg(svg) {
  return svg
    .replace(/<!--[\s\S]*?-->/g, '') // strip comments
    .replace(/\r?\n/g, ' ')           // newlines → space
    .replace(/>\s+</g, '><')          // whitespace between tags
    .replace(/\s{2,}/g, ' ')          // run of spaces → single
    .trim();
}

/**
 * Encode an SVG string for use in a CSS data-URI.
 * We use encodeURIComponent (safe, widely supported) for the body.
 * The result is: data:image/svg+xml,<encoded>
 */
function svgToDataUri(svg) {
  // Optimise: replace double-quotes with single-quotes in attribute values
  // so we can use double-quotes around the CSS url() string without escaping.
  const cleaned = svg.replace(/"/g, "'");
  return 'data:image/svg+xml,' + encodeURIComponent(cleaned);
}

const resolved = new Map(); // name → { dataUri, filled }
const warnings = [];

for (const name of [...usedNames].sort()) {
  let svgPath;
  let isFilled = false;

  if (name.endsWith('-filled')) {
    // e.g. "table-filled" → icons/filled/table.svg
    const base = name.slice(0, -'-filled'.length);
    const candidate = path.join(ICONS_FILLED, `${base}.svg`);
    if (fs.existsSync(candidate)) {
      svgPath = candidate;
      isFilled = true;
    } else {
      // Also try the exact name in outline (unlikely, but guard)
      const outline = path.join(ICONS_OUTLINE, `${name}.svg`);
      if (fs.existsSync(outline)) {
        svgPath = outline;
      }
    }
  } else {
    const outline = path.join(ICONS_OUTLINE, `${name}.svg`);
    if (fs.existsSync(outline)) {
      svgPath = outline;
    } else {
      // Try filled as fallback
      const filled = path.join(ICONS_FILLED, `${name}.svg`);
      if (fs.existsSync(filled)) {
        svgPath = filled;
        isFilled = true;
      }
    }
  }

  if (!svgPath) {
    warnings.push(name);
    continue;
  }

  const raw = fs.readFileSync(svgPath, 'utf8');
  const minified = minifySvg(raw);
  resolved.set(name, { dataUri: svgToDataUri(minified), isFilled });
}

// ─── Step 3: Emit CSS ──────────────────────────────────────────────────────────

/**
 * Base .ti rule: replaces the webfont @font-face approach with an
 * inline-block mask placeholder. Each .ti-NAME rule supplies the actual
 * mask-image; this base rule handles sizing and currentColor fill.
 *
 * Sizing notes:
 *   - width/height: 1em → scales with font-size, matching webfont glyph size.
 *   - vertical-align: -0.125em → matches the typical icon-font baseline drop
 *     (same as Bootstrap Icons SVG sprite approach).
 *   - background-color: currentColor → the mask-image cuts out the icon shape;
 *     currentColor fills visible pixels, so icons inherit the text color.
 *   - mask-size: contain → preserve SVG aspect ratio inside the 1em box.
 *   - mask-repeat: no-repeat → never tile.
 *   - mask-position: center → centre the 24px viewBox inside the em box.
 *
 * The webfont .ti rule had: font-family, speak:none, font-style/weight/variant,
 * text-transform:none, line-height:1, -webkit-font-smoothing. All replaced.
 * We keep line-height:1 for inline layout consistency.
 */
const BASE_RULE = `
/*!
 * Tabler Icons SVG (generated) — do not edit by hand.
 * Re-generate via: node scripts/generate-tabler-icons-svg-css.js
 * Or automatically via: npm run build:webpack
 *
 * Replaces @tabler/icons-webfont to eliminate woff2 caching glyph failures
 * in Tabler >=7.6.x (issue #9479). All ti ti-* markup is unchanged.
 */
.ti {
  display: inline-block;
  width: 1em;
  height: 1em;
  line-height: 1;
  vertical-align: -0.125em;
  background-color: currentColor;
  -webkit-mask-repeat: no-repeat;
  mask-repeat: no-repeat;
  -webkit-mask-position: center;
  mask-position: center;
  -webkit-mask-size: contain;
  mask-size: contain;
}
`.trim();

const iconRules = [];
for (const [name, { dataUri }] of [...resolved.entries()].sort()) {
  iconRules.push(`.ti-${name} { -webkit-mask-image: url("${dataUri}"); mask-image: url("${dataUri}"); }`);
}

const output = [BASE_RULE, '', ...iconRules, ''].join('\n');

// ─── Step 4: Write output ──────────────────────────────────────────────────────

fs.mkdirSync(OUT_DIR, { recursive: true });
fs.writeFileSync(OUT_FILE, output, 'utf8');

// ─── Report ───────────────────────────────────────────────────────────────────

const kb = (output.length / 1024).toFixed(1);
console.log(`✅  Tabler Icons SVG CSS generated: ${path.relative(ROOT, OUT_FILE)}`);
console.log(`    ${resolved.size} icons emitted, ${kb} KB (before gzip)`);

if (warnings.length > 0) {
  console.warn(`⚠️   ${warnings.length} ti-* token(s) with no matching SVG (already blank in webfont):`);
  for (const w of warnings) {
    console.warn(`      ti-${w}`);
  }
}
