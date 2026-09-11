/**
 * Shared file discovery for the icon guards (`validate-no-tabler-icons.js`
 * and `validate-fa-icons-exist.js`), so both always look at exactly the same
 * set of hand-written source files.
 */

const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..', '..');
const SCAN_ROOTS = ['src', 'webpack'];
const EXTENSIONS = new Set(['.php', '.js', '.jsx', '.ts', '.tsx', '.html', '.twig', '.css', '.scss']);

// Directory names that are never ours to fix wherever they appear:
// dependencies and Propel-generated model code.
const SKIP_DIR_NAMES = new Set(['node_modules', 'vendor', 'Base', 'Map']);

// Specific trees, matched by their path relative to the repo root. These are
// deliberately *not* matched by bare name: `src/skin/v2/` is webpack build
// output and must be skipped, while the application's own `src/v2/` source
// tree must be scanned (the Pro-only `fa-house-plus` in #9753 lived there).
const SKIP_DIR_PATHS = new Set([
    path.join('src', 'skin', 'external'), // vendored front-end libraries
    path.join('src', 'skin', 'v2'), // webpack build output
]);

function walk(dir, out) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            if (SKIP_DIR_NAMES.has(entry.name) || SKIP_DIR_PATHS.has(path.relative(ROOT, full))) {
                continue;
            }
            walk(full, out);
        } else if (entry.isFile() && EXTENSIONS.has(path.extname(entry.name))) {
            out.push(full);
        }
    }
    return out;
}

/** Absolute paths of every hand-written source file the icon guards check. */
function collectSourceFiles() {
    const files = [];
    for (const scanRoot of SCAN_ROOTS) {
        const abs = path.join(ROOT, scanRoot);
        if (fs.existsSync(abs)) {
            walk(abs, files);
        }
    }
    return files;
}

module.exports = { ROOT, SCAN_ROOTS, EXTENSIONS, collectSourceFiles };
