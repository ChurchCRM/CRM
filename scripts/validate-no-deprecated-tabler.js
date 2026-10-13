#!/usr/bin/env node

/**
 * Fails the build when hand-written source reintroduces Tabler/Bootstrap
 * patterns that Tabler 1.5/1.6 deprecated or removed (#10109):
 *   - `.form-hint` (use Bootstrap `.form-text`)
 *   - `tabler.getColor()` / `tabler.hexToRgba()`
 *   - `*-focus-box-shadow` Sass variables
 *   - Bootstrap 4 data attributes (`data-toggle`, `data-dismiss`, ...) that
 *     Bootstrap 5 ignores; use `data-bs-*`
 */

const fs = require('fs');
const path = require('path');
const { ROOT, collectSourceFiles, stripComments } = require('./lib/icon-source-files');

const PATTERNS = [
    { re: /class\s*=\s*(?:["'`]|\\")[^"'`]*\bform-hint\b/g, what: '`.form-hint` (use `.form-text`)' },
    { re: /\btabler\.(?:getColor|hexToRgba)\s*\(/g, what: 'removed Tabler JS helper' },
    { re: /\$[a-z-]*focus-box-shadow\b/g, what: 'deprecated Tabler Sass variable' },
    {
        re: /\bdata-(?:toggle|dismiss|placement|parent|ride|slide|spy|trigger|backdrop|keyboard)\s*=/g,
        what: 'Bootstrap 4 data attribute (use `data-bs-*`)',
    },
];

const hits = [];
for (const filePath of collectSourceFiles()) {
    const lines = stripComments(fs.readFileSync(filePath, 'utf8')).split('\n');
    lines.forEach((line, index) => {
        for (const { re, what } of PATTERNS) {
            re.lastIndex = 0;
            if (re.test(line)) {
                hits.push({ filePath: path.relative(ROOT, filePath), line: index + 1, what, text: line.trim() });
                return;
            }
        }
    });
}

if (hits.length > 0) {
    console.error(`❌ Found ${hits.length} deprecated Tabler/Bootstrap pattern(s):\n`);
    for (const { filePath, line, what, text } of hits) {
        console.error(`  ${filePath}:${line} — ${what}\n    ${text}\n`);
    }
    process.exit(1);
}
console.log('✨ No deprecated Tabler patterns found.');
