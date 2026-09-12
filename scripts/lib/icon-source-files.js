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

/**
 * Blanks out comment text so the icon guards never flag a class name that is
 * only *mentioned* — migration notes such as
 * `/* was fa-house-plus (Pro-only) *\/` are documentation, not markup, and a
 * guard that fails on them punishes contributors for explaining themselves.
 *
 * Comment characters are replaced with spaces rather than removed, so line and
 * column numbers reported to the user still match the file on disk. String
 * literals are walked over untouched: `'fa-house-plus'` in real code must
 * still be caught, and a `//` or `/*` inside a string must not start a
 * comment. Handles the comment syntaxes shared by every scanned extension:
 * `/* *\/`, `//`, `<!-- -->`, and a `#` line comment in PHP. Also recognizes
 * PHP heredocs and nowdocs (`<<<LABEL ... LABEL;`) to avoid blanking literal
 * text (such as URLs with `://`) inside them.
 */
function stripComments(source) {
    const out = source.split('');
    const n = source.length;
    let i = 0;

    const blank = (from, to) => {
        for (let k = from; k < to && k < n; k++) {
            if (out[k] !== '\n') {
                out[k] = ' ';
            }
        }
    };

    while (i < n) {
        const c = source[i];

        // PHP heredocs / nowdocs: `<<<LABEL ... LABEL;`
        // Recognizable by `<` followed by `<<` and an identifier.
        if (c === '<' && source[i + 1] === '<' && source[i + 2] === '<') {
            let j = i + 3;
            // Skip optional quote for nowdoc
            const quoted = source[j] === "'" || source[j] === '"';
            if (quoted) j++;
            // Collect the label (word characters)
            const labelStart = j;
            while (j < n && /[A-Za-z0-9_]/.test(source[j])) j++;
            const label = source.substring(labelStart, j);
            if (quoted) j++; // closing quote
            // Find the label on its own line (with optional semicolon after)
            const labelPattern = new RegExp(`(^|\n)${label}\\s*;`, 'm');
            const match = source.slice(j).match(labelPattern);
            if (match && label.length > 0) {
                const endPos = j + match.index + match[0].length;
                i = endPos;
                continue;
            }
            // If we couldn't find the end, skip the `<<<` and continue normally
            i++;
            continue;
        }

        // String literals: copy through, honouring backslash escapes.
        if (c === '"' || c === "'" || c === '`') {
            i++;
            while (i < n && source[i] !== c) {
                i += source[i] === '\\' ? 2 : 1;
            }
            i++;
            continue;
        }

        if (c === '/' && source[i + 1] === '*') {
            const end = source.indexOf('*/', i + 2);
            const stop = end === -1 ? n : end + 2;
            blank(i, stop);
            i = stop;
            continue;
        }

        // `//` — but not the `//` of a scheme (`https://`), which is inside a
        // string in practice but may also appear bare in a CSS url().
        if (c === '/' && source[i + 1] === '/' && source[i - 1] !== ':') {
            const end = source.indexOf('\n', i);
            const stop = end === -1 ? n : end;
            blank(i, stop);
            i = stop;
            continue;
        }

        if (c === '<' && source.startsWith('<!--', i)) {
            const end = source.indexOf('-->', i + 4);
            const stop = end === -1 ? n : end + 3;
            blank(i, stop);
            i = stop;
            continue;
        }

        // `#` starts a PHP line comment only at the start of a line; anywhere
        // else it is a CSS id selector, a colour literal, or a fragment URL.
        if (c === '#' && /(^|\n)[ \t]*$/.test(source.slice(Math.max(0, i - 40), i))) {
            const end = source.indexOf('\n', i);
            const stop = end === -1 ? n : end;
            blank(i, stop);
            i = stop;
            continue;
        }

        i++;
    }

    return out.join('');
}

module.exports = { ROOT, SCAN_ROOTS, EXTENSIONS, collectSourceFiles, stripComments };
