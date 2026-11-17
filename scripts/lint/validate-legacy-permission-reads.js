#!/usr/bin/env node

/**
 * Fails when new code reads the old staff permission switches outside User.
 *
 * Existing hits live in legacy-permission-allowlist.json as "file:line:kind".
 * A hit that is not on that list fails. Dropping a hit is allowed.
 * --update rewrites the list from the current tree. npm run lint does not pass it.
 *
 * A token only inside a comment, or only inside a gettext/_() string, is not a hit.
 * Propel Base/ and Map/ are gitignored and regenerated, so their line numbers
 * cannot be allowlist entries. Vendor is skipped for the same reason.
 */

const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..', '..');
const SRC = path.join(ROOT, 'src');
const ALLOWLIST_FILE = path.join(__dirname, 'legacy-permission-allowlist.json');
const USER_FILE = 'src/ChurchCRM/model/ChurchCRM/User.php';
const SKIP_PREFIXES = [
    'src/ChurchCRM/model/ChurchCRM/Base/',
    'src/ChurchCRM/model/ChurchCRM/Map/',
    'src/vendor/',
];
const SKIP_DIRS = new Set(['vendor', 'node_modules']);

const IDENTIFIERS = [
    'usr_Admin',
    'usr_AddRecords',
    'usr_EditRecords',
    'usr_DeleteRecords',
    'usr_ManageGroups',
    'usr_Finance',
    'usr_ManageFundraisers',
    'usr_ManageMinistries',
    'usr_ManageMyMinistries',
    'usr_Notes',
    'bAddEvent',
    'bEmailMailto',
];

const IS_ADMIN = /(?:->|::)\s*isAdmin\s*\(/;
const IDENTIFIER_RE = new RegExp(`\\b(?:${IDENTIFIERS.join('|')})\\b`, 'g');
const TRANSLATION_RE = /\b(?:gettext|ngettext|dgettext|dngettext|pgettext|__|_)\s*\(/g;

function relative(file) {
    return path.relative(ROOT, file).split(path.sep).join('/');
}

function isSkippedPath(rel) {
    return SKIP_PREFIXES.some((prefix) => rel === prefix.slice(0, -1) || rel.startsWith(prefix));
}

function walk(dir, out = []) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        if (entry.isSymbolicLink()) {
            continue;
        }
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            if (SKIP_DIRS.has(entry.name) || isSkippedPath(relative(full))) {
                continue;
            }
            walk(full, out);
        } else if (entry.isFile() && entry.name.endsWith('.php')) {
            out.push(full);
        }
    }
    return out;
}

function codeLines(source) {
    const lines = source.split(/\r?\n/);
    let inBlock = false;
    let inHtml = false;
    return lines.map((line) => {
        let out = '';
        let quote = null;
        for (let i = 0; i < line.length; i++) {
            const ch = line[i];
            const next = line[i + 1];
            if (inHtml) {
                if (ch === '-' && line.startsWith('-->', i)) {
                    inHtml = false;
                    i += 2;
                }
                continue;
            }
            if (inBlock) {
                if (ch === '*' && next === '/') {
                    inBlock = false;
                    i += 1;
                }
                continue;
            }
            if (quote) {
                out += ch;
                if (ch === '\\' && i + 1 < line.length) {
                    out += line[i + 1];
                    i += 1;
                    continue;
                }
                if (ch === quote) {
                    quote = null;
                }
                continue;
            }
            if (ch === '/' && next === '/') {
                break;
            }
            if (ch === '#' && next !== '[') {
                break;
            }
            if (ch === '/' && next === '*') {
                inBlock = true;
                i += 1;
                continue;
            }
            if (ch === '<' && line.startsWith('<!--', i)) {
                inHtml = true;
                i += 3;
                continue;
            }
            if (ch === "'" || ch === '"') {
                quote = ch;
            }
            out += ch;
        }
        return out;
    });
}

function stripTranslationStrings(line) {
    const blanks = [];
    TRANSLATION_RE.lastIndex = 0;
    let match = TRANSLATION_RE.exec(line);
    while (match !== null) {
        let depth = 1;
        let quote = null;
        let stringStart = -1;
        for (let i = match.index + match[0].length; i < line.length && depth > 0; i++) {
            const ch = line[i];
            if (quote) {
                if (ch === '\\') {
                    i += 1;
                    continue;
                }
                if (ch === quote) {
                    blanks.push([stringStart, i + 1]);
                    quote = null;
                }
                continue;
            }
            if (ch === "'" || ch === '"') {
                quote = ch;
                stringStart = i;
            } else if (ch === '(') {
                depth += 1;
            } else if (ch === ')') {
                depth -= 1;
            }
        }
        match = TRANSLATION_RE.exec(line);
    }
    if (blanks.length === 0) {
        return line;
    }
    let result = line;
    for (const [start, end] of blanks.reverse()) {
        result = result.slice(0, start) + result.slice(end);
    }
    return result;
}

function kindsOnLine(code) {
    const text = stripTranslationStrings(code);
    const kinds = new Set();
    if (IS_ADMIN.test(text)) {
        kinds.add('isAdmin');
    }
    IDENTIFIER_RE.lastIndex = 0;
    let match = IDENTIFIER_RE.exec(text);
    while (match !== null) {
        kinds.add(match[0]);
        match = IDENTIFIER_RE.exec(text);
    }
    return [...kinds].sort((a, b) => a.localeCompare(b));
}

function findHits(relativePath, source) {
    if (relativePath === USER_FILE || isSkippedPath(relativePath)) {
        return [];
    }
    const hits = [];
    codeLines(source).forEach((code, index) => {
        for (const kind of kindsOnLine(code)) {
            hits.push(`${relativePath}:${index + 1}:${kind}`);
        }
    });
    return hits;
}

function compareHits(a, b) {
    const parse = (hit) => {
        const match = /^(.*):(\d+):([^:]+)$/.exec(hit);
        return match
            ? { file: match[1], line: Number(match[2]), kind: match[3] }
            : { file: hit, line: 0, kind: '' };
    };
    const left = parse(a);
    const right = parse(b);
    return left.file.localeCompare(right.file) || left.line - right.line || left.kind.localeCompare(right.kind);
}

function scan() {
    const hits = walk(SRC).flatMap((file) => findHits(relative(file), fs.readFileSync(file, 'utf8')));
    hits.sort(compareHits);
    return hits;
}

function loadAllowlist() {
    const raw = JSON.parse(fs.readFileSync(ALLOWLIST_FILE, 'utf8'));
    if (!Array.isArray(raw) || raw.some((entry) => typeof entry !== 'string')) {
        throw new Error(`${path.basename(ALLOWLIST_FILE)} must be a list of file:line:kind strings`);
    }
    return raw;
}

function main() {
    const hits = scan();
    if (process.argv.includes('--update')) {
        fs.writeFileSync(ALLOWLIST_FILE, `${JSON.stringify(hits, null, 2)}\n`);
        console.log(`Wrote ${hits.length} legacy permission read(s).`);
        return;
    }

    let allowed;
    try {
        allowed = new Set(loadAllowlist());
    } catch (error) {
        if (error.code === 'ENOENT') {
            console.error('Missing scripts/lint/legacy-permission-allowlist.json. Run with --update to create it.');
            process.exit(1);
        }
        throw error;
    }

    const fresh = hits.filter((hit) => !allowed.has(hit));
    if (fresh.length > 0) {
        console.error(`Legacy permission reads: ${fresh.length} new hit(s) outside User.php:`);
        for (const hit of fresh) {
            console.error(`  ${hit}`);
        }
        process.exit(1);
    }
    console.log(`Legacy permission reads: ${hits.length} existing hit(s) are allowlisted.`);
}

if (require.main === module) {
    main();
}

module.exports = { findHits };
