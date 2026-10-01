#!/usr/bin/env node
// Every step `uses:` must be a local path or owner/repo@<40-hex SHA>.
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..', '.github');
const files = [];
for (const dir of ['workflows', 'actions']) {
    const walk = (d) => {
        if (!fs.existsSync(d)) return;
        for (const e of fs.readdirSync(d, { withFileTypes: true })) {
            const p = path.join(d, e.name);
            if (e.isDirectory()) walk(p);
            else if (/\.ya?ml$/.test(e.name)) files.push(p);
        }
    };
    walk(path.join(root, dir));
}

const bad = [];
for (const f of files) {
    fs.readFileSync(f, 'utf8')
        .split('\n')
        .forEach((line, i) => {
            const m = line.match(/^\s*(?:-\s+)?uses:\s*(\S+)/);
            if (!m || !/[/@]/.test(m[1])) return;
            if (!/^\.\//.test(m[1]) && !/^[^\s@]+@[0-9a-f]{40}$/.test(m[1])) bad.push(`${path.relative(root, f)}:${i + 1}: ${m[1]}`);
        });
}

if (bad.length) {
    console.error('Unpinned GitHub Actions (use owner/repo@<40-hex SHA> # vX or a ./ path):');
    bad.forEach((b) => console.error(`  ${b}`));
    process.exit(1);
}
console.log(`All actions pinned (${files.length} files checked).`);
