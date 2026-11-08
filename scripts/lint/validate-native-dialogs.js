#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { ROOT, collectSourceFiles, stripComments } = require('./lib/icon-source-files');

function findNativeDialogs(source) {
    const native = /(?<![\w$]|[.?]\s*)(?:(?:window|globalThis|self)\s*(?:\?\.|\.)\s*)?(?:confirm|alert|prompt)\s*(?:\?\.)?\s*\(/g;
    const direct = /(?:window\s*\.\s*)?bootbox\s*(?:\?\.|\.)\s*(?:confirm|alert|prompt)\s*\(/g;
    const stripped = stripComments(source);
    const lines = source.split('\n');
    const hit = (match) => ({ line: stripped.slice(0, match.index).split('\n').length, call: match[0] });
    const exempt = ({ line }) => /confirm-action-exempt/.test(lines[line - 2] || '');
    return [
        ...Array.from(stripped.matchAll(native), hit),
        ...Array.from(stripped.matchAll(direct), hit).filter((found) => !exempt(found)),
    ].sort((a, b) => a.line - b.line);
}

if (require.main === module) {
    const files = process.argv.length > 2 ? process.argv.slice(2) : collectSourceFiles();
    const wrapper = path.join('webpack', 'common', 'confirm-action.ts');
    let failures = 0;
    for (const file of files) {
        if (!/\.(?:php|[cm]?js|jsx|tsx?|html|twig)$/.test(file) || file.endsWith('.d.ts') || file.endsWith(wrapper)) continue;
        for (const hit of findNativeDialogs(fs.readFileSync(file, 'utf8'))) {
            console.error(`${path.relative(ROOT, file)}:${hit.line}: ${hit.call} — use confirmAction(), alertAction() or CRM.notify().`);
            failures++;
        }
    }
    process.exitCode = failures ? 1 : 0;
}

module.exports = { findNativeDialogs };
