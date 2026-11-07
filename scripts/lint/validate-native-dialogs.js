#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { ROOT, collectSourceFiles, stripComments } = require('./lib/icon-source-files');

function findNativeDialogs(source) {
    const pattern = /(?<![\w$]|[.?]\s*)(?:(?:window|globalThis|self)\s*(?:\?\.|\.)\s*)?(?:confirm|alert)\s*(?:\?\.)?\s*\(/g;
    return Array.from(stripComments(source).matchAll(pattern), (match) => ({
        line: source.slice(0, match.index).split('\n').length,
        call: match[0],
    }));
}

if (require.main === module) {
    const files = process.argv.length > 2 ? process.argv.slice(2) : collectSourceFiles();
    let failures = 0;
    for (const file of files) {
        if (!/\.(?:php|[cm]?js|jsx|tsx?|html|twig)$/.test(file) || file.endsWith('.d.ts')) continue;
        for (const hit of findNativeDialogs(fs.readFileSync(file, 'utf8'))) {
            console.error(`${path.relative(ROOT, file)}:${hit.line}: ${hit.call} — use confirmAction() or CRM.notify().`);
            failures++;
        }
    }
    process.exitCode = failures ? 1 : 0;
}

module.exports = { findNativeDialogs };
