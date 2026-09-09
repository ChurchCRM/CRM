#!/usr/bin/env node

/**
 * Verifies every `use ChurchCRM\...;` import statement in the codebase
 * references its target file with exactly the right case.
 *
 * This is the complement to validate-namespace-case.js: that script checks
 * a file's own namespace declaration against its own directory; this one
 * checks every *consumer* of ChurchCRM\* classes against the real,
 * case-exact location of what they're importing. A file can have a
 * perfectly correct namespace declaration while still being imported
 * elsewhere with the wrong case in the `use` statement — exactly what
 * happened in src/finance/routes/pledges.php after the ChurchCRM\Utils
 * rename in #9668: `use ChurchCRM\utils\FiscalYearUtils;` (lowercase)
 * survived a `grep` for forward-slash paths because it's written with
 * backslashes.
 *
 * On case-insensitive filesystems (macOS, Windows) PHP happily resolves a
 * wrong-case `use` statement anyway, so this class of typo is invisible
 * until it hits a case-sensitive Linux deployment with a stale classmap.
 *
 * Scoped to the ChurchCRM\ PSR-4 root declared in composer.json.
 */

const fs = require('fs');
const path = require('path');

const SRC_ROOT = path.join(__dirname, '..', 'src');
const CHURCHCRM_ROOT = path.join(SRC_ROOT, 'ChurchCRM');
const SKIP_DIRS = new Set(['vendor', 'node_modules', '.git']);

console.log('🔍 Use-Statement Case Validation');
console.log('=================================\n');

function walk(dir, out) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        if (entry.isDirectory()) {
            if (SKIP_DIRS.has(entry.name)) {
                continue;
            }
            walk(path.join(dir, entry.name), out);
        } else if (entry.isFile() && entry.name.endsWith('.php')) {
            out.push(path.join(dir, entry.name));
        }
    }
    return out;
}

// Case-insensitive map of every real file under src/ChurchCRM/, keyed by
// its path relative to ChurchCRM/ in lowercase, valued with its real case.
function buildRealPathIndex() {
    const index = new Map();
    for (const filePath of walk(CHURCHCRM_ROOT, [])) {
        const relative = path.relative(CHURCHCRM_ROOT, filePath);
        index.set(relative.toLowerCase(), relative);
    }
    return index;
}

const realPaths = buildRealPathIndex();
const phpFiles = walk(SRC_ROOT, []);

console.log(`📋 Scanning ${phpFiles.length} PHP file(s) for ChurchCRM\\ use statements\n`);

const mismatches = [];
const useStatementPattern = /^\s*use\s+(ChurchCRM\\[\w\\]+);/gm;

for (const filePath of phpFiles) {
    const content = fs.readFileSync(filePath, 'utf8');
    let match;
    while ((match = useStatementPattern.exec(content)) !== null) {
        const fqcn = match[1];
        const segments = fqcn.split('\\').slice(1); // drop leading "ChurchCRM"
        const relativePath = `${segments.join(path.sep)}.php`;
        const realPath = realPaths.get(relativePath.toLowerCase());

        if (realPath === undefined) {
            continue; // not found at all — a different concern (dangling reference), not a case issue
        }
        if (realPath !== relativePath) {
            mismatches.push({
                filePath: path.relative(process.cwd(), filePath),
                fqcn,
                expected: `ChurchCRM\\${realPath.split(path.sep).join('\\').replace(/\.php$/, '')}`,
            });
        }
    }
}

if (mismatches.length > 0) {
    console.error(`❌ Found ${mismatches.length} use-statement case mismatch(es):\n`);
    for (const { filePath, fqcn, expected } of mismatches) {
        console.error(`  ${filePath}`);
        console.error(`    found:    use ${fqcn};`);
        console.error(`    expected: use ${expected}; (matching the real file's case exactly)\n`);
    }
    process.exit(1);
}

console.log('✨ Every ChurchCRM\\ use statement matches its target file\'s case exactly!');
