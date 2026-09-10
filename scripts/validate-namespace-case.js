#!/usr/bin/env node

/**
 * Verifies every PHP file's declared `namespace` matches its own directory
 * path exactly, case-sensitively.
 *
 * PHP's PSR-4 autoloading only tolerates a namespace/directory case
 * mismatch on case-insensitive filesystems (macOS, Windows) — it breaks
 * outright on case-sensitive ones (most real Linux deployments) whenever
 * Composer's classmap doesn't already have a fresh entry for the affected
 * class. See issue #9668: `ChurchCRM\Utils\ImageSupportUtils` lived in
 * `ChurchCRM/utils/` (lowercase) for months, invisible everywhere until a
 * genuinely case-sensitive filesystem exposed it.
 *
 * Scoped to src/ChurchCRM/ — the one PSR-4-mapped root in composer.json
 * ("ChurchCRM\\": "ChurchCRM/"). Skips Base/ and Map/, which are
 * Propel-generated (gitignored, regenerated at build time) rather than
 * hand-maintained source.
 */

const fs = require('fs');
const path = require('path');

const CHURCHCRM_ROOT = path.join(__dirname, '..', 'src', 'ChurchCRM');
const SKIP_DIRS = new Set(['Base', 'Map', 'vendor']);

console.log('🔍 Namespace/Directory Case Validation');
console.log('=======================================\n');

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

function declaredNamespace(filePath) {
    const content = fs.readFileSync(filePath, 'utf8');
    const match = content.match(/^\s*namespace\s+([^;]+);/m);
    return match ? match[1].trim() : null;
}

const files = walk(CHURCHCRM_ROOT, []);
console.log(`📋 Checking ${files.length} PHP file(s) under src/ChurchCRM/\n`);

const mismatches = [];

for (const filePath of files) {
    const ns = declaredNamespace(filePath);
    if (!ns) {
        continue; // no namespace declared — not this check's concern
    }

    // Expected namespace, derived from the file's actual path, case-exact.
    const relativeDir = path.relative(path.dirname(CHURCHCRM_ROOT), path.dirname(filePath));
    const expectedNs = relativeDir.split(path.sep).join('\\');

    if (ns !== expectedNs) {
        mismatches.push({ filePath: path.relative(process.cwd(), filePath), ns, expectedNs });
    }
}

if (mismatches.length > 0) {
    console.error(`❌ Found ${mismatches.length} namespace/directory case mismatch(es):\n`);
    for (const { filePath, ns, expectedNs } of mismatches) {
        console.error(`  ${filePath}`);
        console.error(`    declared: namespace ${ns};`);
        console.error(`    expected: namespace ${expectedNs}; (from its actual directory path)\n`);
    }
    console.error('Fix: rename the directory (or move the file) to match its declared namespace exactly.');
    process.exit(1);
}

console.log('✨ Every namespace declaration matches its directory path exactly!');
