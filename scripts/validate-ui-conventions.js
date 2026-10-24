#!/usr/bin/env node

/**
 * Keeps the UI on stock Tabler/Bootstrap 5 and keeps styling in the pipeline.
 * Rules and rationale: .agents/skills/churchcrm/frontend-development.md
 *
 *  1. Static `style="..."` attributes: no new ones. A value computed from data
 *     (`<?=`, `{{ }}`, `${ }`) is fine. Per-file counts of the remaining static
 *     ones (state that JS toggles, setup pages) live in
 *     scripts/ui-conventions-baseline.json; a file may not exceed its count.
 *  2. Page-level `<style>` blocks: none outside the baseline.
 *  3. Bootstrap 4 class names in markup (ml-2, text-right, badge-success, sr-only, ...).
 *     Bootstrap 5 ignores them, so they render unstyled.
 *  4. CSS: no root font-size, theme-base, palette or font overrides, and no
 *     Bootstrap 4 selectors (.custom-control, .form-group, .input-group-addon ...).
 *  5. Patterns Tabler 1.5/1.6 removed: .form-hint, tabler.getColor()/hexToRgba(),
 *     *-focus-box-shadow Sass variables, Bootstrap 4 data-toggle-style attributes.
 *     Also the fixed-colour table-success/-warning/... variants, which keep black text in dark mode.
 */

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const BASELINE_FILE = path.join(__dirname, 'ui-conventions-baseline.json');
const SKIP = ['skin/v2/', 'skin/external', '/vendor/', 'node_modules', '/locale/', 'templates/email', '.min.'];
const MARKUP = /\.(php|twig|js|ts|html)$/;
const STYLES = /\.(css|scss)$/;
const SKIP_DIR = /(?:^|\/)(?:Base|Map)\//;

function walk(dir, out = []) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        const rel = path.relative(ROOT, full) + (entry.isDirectory() ? '/' : '');
        if (SKIP.some((s) => rel.includes(s)) || SKIP_DIR.test(rel)) continue;
        if (entry.isDirectory()) walk(full, out);
        else out.push(full);
    }
    return out;
}

const files = [...walk(path.join(ROOT, 'src')), ...walk(path.join(ROOT, 'webpack'))];
function readBaseline() {
    try {
        return JSON.parse(fs.readFileSync(BASELINE_FILE, 'utf8'));
    } catch (error) {
        if (error.code === 'ENOENT') return { inlineStyles: {} };
        throw error;
    }
}

const baseline = readBaseline();
const update = process.argv.includes('--update-baseline');

const dynamic = /<\?|\{\{|\{%|\$\{|\\\$|["'`]\s*\+|\+\s*["'`]/;
const bs4Class = new RegExp(
    String.raw`class\s*=\s*(["'` + '`' + String.raw`])[^"'` + '`' + String.raw`]*(?<![\w-])(` +
        [
            '[mp][lr]-(?:[0-5]|auto)',
            'text-(?:left|right)',
            'float-(?:left|right)',
            'pull-(?:left|right)',
            'font-weight-(?:bold|normal|light)',
            'badge-(?:primary|secondary|success|danger|warning|info|light|dark)',
            'sr-only',
            'no-gutters',
            'form-group',
            'btn-default',
            'input-group-(?:append|prepend|addon)',
            'custom-(?:control(?:-\\w+)?|checkbox|radio|select|file)',
        ].join('|') +
        String.raw`)(?![\w-])`,
);
const stylePatterns = [
    { re: /(?:^|[}\s,])(?:html|:root|body)\s*(?:,\s*(?:html|:root|body)\s*)*\{[^}]*\bfont-size\s*:/m, what: 'root/body font-size override (Tabler sets it)' },
    { re: /--tblr-(?:primary|secondary|success|danger|warning|info|font-sans-serif|body-bg|body-color)\s*:/, what: 'Tabler palette/font variable redefined (use data-bs-theme-primary for the accent)' },
    { re: /data-bs-theme-base/, what: 'theme-base override (stock Tabler greys)' },
    { re: /\.(?:custom-control(?:-\w+)?|custom-checkbox|form-group|input-group-addon|btn-default|pull-(?:left|right)|panel(?:-\w+)?)(?![\w-])/, what: 'Bootstrap 4 selector' },
];

const removedMarkup = [
    { re: /class\s*=\s*(?:["'`]|\\")[^"'`]*\bform-hint\b/, what: '`.form-hint` (use `.form-text`)' },
    { re: /\btabler\.(?:getColor|hexToRgba)\s*\(/, what: 'removed Tabler JS helper' },
    { re: /\bdata-(?:toggle|dismiss|placement|parent|ride|slide|spy|trigger|backdrop|keyboard)\s*=/, what: 'Bootstrap 4 data attribute (use `data-bs-*`)' },
    { re: /(?<![\w-])table-(?:primary|secondary|success|danger|warning|info|light|dark)(?![\w-])/, what: 'fixed-colour table variant (black text in dark mode); use `table-active` or a `bg-*-lt` utility' },
];
const focusShadow = /\$[a-z-]*focus-box-shadow\b/;

const inline = {};
const blocks = {};
const problems = [];

for (const file of files) {
    const rel = path.relative(ROOT, file);
    const text = fs.readFileSync(file, 'utf8');

    if (MARKUP.test(file)) {
        for (const m of text.matchAll(/style\s*=\s*(["'])([\s\S]*?)\1/g)) {
            if (!dynamic.test(m[2])) inline[rel] = (inline[rel] || 0) + 1;
        }
        const n = (text.match(/<style\b/g) || []).length;
        if (n && !rel.endsWith('Include/Header.php') && !rel.endsWith('ChurchCRM/Bootstrapper.php')) blocks[rel] = n;
        text.split('\n').forEach((line, i) => {
            if (bs4Class.test(line)) problems.push(`${rel}:${i + 1} Bootstrap 4 class name\n    ${line.trim().slice(0, 140)}`);
            for (const { re, what } of removedMarkup) {
                if (re.test(line)) problems.push(`${rel}:${i + 1} ${what}\n    ${line.trim().slice(0, 140)}`);
            }
        });
    }
    if (STYLES.test(file)) {
        const stripped = text.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');
        for (const { re, what } of stylePatterns) {
            if (re.test(stripped)) problems.push(`${rel} ${what}`);
        }
        if (focusShadow.test(stripped)) problems.push(`${rel} deprecated Tabler Sass variable (*-focus-box-shadow)`);
    }
}

if (update) {
    fs.writeFileSync(BASELINE_FILE, JSON.stringify({ inlineStyles: inline }, null, 2) + '\n');
    console.log(`Baseline written: ${Object.keys(inline).length} files with static inline styles.`);
    process.exit(0);
}

for (const [rel, n] of Object.entries(inline)) {
    const allowed = baseline.inlineStyles[rel] || 0;
    if (n > allowed) problems.push(`${rel} has ${n} static style="" attribute(s), baseline allows ${allowed}. Use a Tabler utility or a class in src/skin/scss/_page-<area>.scss`);
}
for (const [rel, n] of Object.entries(blocks)) {
    problems.push(`${rel} has ${n} <style> block(s). Move the rules to src/skin/scss/_page-<area>.scss`);
}

console.log('UI conventions check');
if (problems.length) {
    console.error(`\n❌ ${problems.length} problem(s):\n`);
    for (const p of problems) console.error('  ' + p);
    console.error('\nRules: .agents/skills/churchcrm/frontend-development.md');
    process.exit(1);
}
console.log('✨ Styling stays in utilities, page partials and stock Tabler.');
