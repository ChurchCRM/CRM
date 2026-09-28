#!/usr/bin/env node

/**
 * Fails the build if any hand-written source file references a Font Awesome
 * class (`fa-*`) that does not exist in the Font Awesome build the app
 * actually ships: `@fortawesome/fontawesome-free`.
 *
 * Companion to `validate-no-tabler-icons.js` (#9752). That script catches
 * icons from a library we no longer ship at all; this one catches the more
 * subtle variant found in #9753: the class *is* Font Awesome, but the glyph
 * is Pro-only, so it is absent from the Free `all.css` and renders as blank
 * space. Both fail silently at runtime — no console error, no build warning.
 *
 * How it works
 * ------------
 * `all.css` is the single source of truth. Every icon in the Free build emits
 * a `.fa-<name> { --fa: "\fXXX" }` rule (FA 7.x), and every *utility* class
 * (`fa-solid`, `fa-fw`, `fa-lg`, `fa-spin`, `fa-rotate-90`, ...) is likewise a
 * `.fa-<token>` rule. So the allow-list is simply "every `.fa-<token>`
 * selector in `all.css`" — there is no hand-maintained list of style tokens
 * to drift out of date.
 *
 * Dynamic class names
 * -------------------
 * A token that is only a prefix — `fa-` immediately followed by a variable
 * (`'fa-' . $icon`, `` `fa-${name}` ``, `fa-chevron-<?= $dir ?>`) — cannot be
 * checked statically and is ignored. That is why dynamic icon maps must hold
 * *complete* class names rather than assembling them from fragments: only a
 * complete name is visible to this guard. #9752 already converted the search
 * result icon maps to full names for exactly this reason.
 */

const fs = require('fs');
const path = require('path');

const { ROOT, SCAN_ROOTS, collectSourceFiles, stripComments } = require('./lib/icon-source-files');

const FA_CSS = path.join(ROOT, 'node_modules', '@fortawesome', 'fontawesome-free', 'css', 'all.css');

// `fa-foo`, `fa-foo-bar`: not preceded by a word character or a hyphen (so
// `data-fa-transform` and `sofa-bed` are not matches). A trailing hyphen is
// captured deliberately — it marks a dynamically completed name, skipped below.
const TOKEN_RE = /(?<![\w-])fa-[a-z0-9]+(?:-[a-z0-9]+)*-?/g;

// A `.fa-foo` selector, i.e. followed by whatever may legally end a class
// selector. Used both on `all.css` and on our own stylesheets.
const SELECTOR_RE = /\.(fa-[a-z0-9]+(?:-[a-z0-9]+)*)(?![\w-])/g;

function matchAll(source, re) {
    const found = new Set();
    re.lastIndex = 0;
    let match;
    while ((match = re.exec(source)) !== null) {
        found.add(match[1]);
    }
    return found;
}

function loadShippedClasses() {
    if (!fs.existsSync(FA_CSS)) {
        console.error(`❌ Cannot find the shipped Font Awesome stylesheet: ${path.relative(ROOT, FA_CSS)}`);
        console.error('   Run `npm ci` first — this check needs the real dependency, not a guess.');
        process.exit(1);
    }
    return matchAll(fs.readFileSync(FA_CSS, 'utf8'), SELECTOR_RE);
}

// Classes the project defines itself (a `.fa-something` rule in one of our own
// stylesheets) are legitimate even though Font Awesome never heard of them.
function collectLocallyDefinedClasses(files) {
    const classes = new Set();
    for (const filePath of files) {
        if (filePath.endsWith('.css') || filePath.endsWith('.scss')) {
            const source = stripComments(fs.readFileSync(filePath, 'utf8'));
            for (const name of matchAll(source, SELECTOR_RE)) {
                classes.add(name);
            }
        }
    }
    return classes;
}

console.log('🔍 Font Awesome Icon Existence Validation');
console.log('=========================================\n');

const shipped = loadShippedClasses();
console.log(`📦 ${shipped.size} \`.fa-*\` rule(s) in the shipped Free build (icons + utility classes)`);

const files = collectSourceFiles();
const known = new Set([...shipped, ...collectLocallyDefinedClasses(files)]);
console.log(`📋 Checking ${files.length} file(s) under ${SCAN_ROOTS.join('/, ')}/\n`);

const hits = [];
let dynamicCount = 0;

for (const filePath of files) {
    // Comments are blanked (line numbers preserved) so a migration note such
    // as `/* was fa-house-plus — Pro-only */` is documentation, not a hit.
    const lines = stripComments(fs.readFileSync(filePath, 'utf8')).split('\n');
    lines.forEach((line, index) => {
        TOKEN_RE.lastIndex = 0;
        let match;
        while ((match = TOKEN_RE.exec(line)) !== null) {
            const token = match[0];
            if (token.endsWith('-')) {
                // `fa-` prefix completed at runtime — nothing to verify.
                dynamicCount++;
                continue;
            }
            if (known.has(token)) {
                continue;
            }
            hits.push({
                filePath: path.relative(ROOT, filePath),
                line: index + 1,
                token,
                text: line.trim(),
            });
        }
    });
}

if (dynamicCount > 0) {
    console.log(`ℹ️  Ignored ${dynamicCount} dynamically completed \`fa-\` prefix(es).`);
    console.log('   Icon maps must store complete class names so they stay checkable.\n');
}

if (hits.length > 0) {
    console.error(`❌ Found ${hits.length} Font Awesome class(es) that the shipped build does not define:\n`);
    for (const { filePath, line, token, text } of hits) {
        console.error(`  ${filePath}:${line} — \`${token}\``);
        console.error(`    ${text}\n`);
    }
    console.error('These are typically Pro-only glyphs or typos; either way they render as blank space.');
    console.error('Fix: pick a free equivalent. List the shipped set with');
    console.error("  grep -o '\\.fa-[a-z0-9-]*' node_modules/@fortawesome/fontawesome-free/css/all.css | sort -u");
    console.error('and see .agents/skills/churchcrm/icon-management.md for the project mapping tables.');
    process.exit(1);
}

console.log('✨ Every Font Awesome class used in the source exists in the shipped build!');
