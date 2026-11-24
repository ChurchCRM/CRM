'use strict';

/**
 * Terms guard: run the terms review at code-review time, not after merge.
 *
 * The terms PR (locale-terms-diff.js) flags odd new strings only once they have been extracted
 * onto master. This guard reads a PR's diff instead: it pulls the string literals the PR adds
 * to gettext() / ngettext() / t() calls under src/ and webpack/, keeps the ones that are not in
 * the base catalog (locale/messages.po at the base rev), and runs the same checks the terms
 * review uses (locale-terms-diff's suspicions), plus two diff-only checks:
 *   - a new term that near-duplicates ANOTHER term added by the same PR (the casing pairs —
 *     neither is in the catalog yet, so the post-merge check is the first that could see both);
 *   - a new term similar to a catalog term the same PR removes (a rewording that would orphan
 *     the existing translations).
 *
 * Usage: node locale/scripts/lib/locale-terms-guard.js [--base <rev>] [--head <rev>] [--report <file>]
 *        node locale/scripts/lib/locale-terms-guard.js --staged   (pre-commit: the staged diff against HEAD's catalog)
 * Exit 1 when anything is flagged, 0 when the PR adds no questionable new terms.
 * The escape hatch for intentional variants is the `locale-terms-ok` PR label (the workflow
 * still posts the report as a comment; it just does not fail the check).
 */

const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const { parsePoEntries } = require('./locale-plurals');
const { suspicions, similar, normalize } = require('./locale-terms-diff');

const ROOT = path.join(__dirname, '../../..');
const PO_FILE = 'locale/messages.po';
const DIFF_PATHS = ['src', 'webpack', ':!src/locale', ':!src/skin/external', ':!src/vendor', ':!*.min.js', ':!*.map'];
const MAX_LOCATIONS = 3;

const LITERAL = String.raw`(['"])((?:\\.|(?!\1)[^\\])*)\1`;
const CALL_PATTERNS = [
    { re: new RegExp(String.raw`\bngettext\(\s*${LITERAL}\s*,\s*(['"])((?:\\.|(?!\3)[^\\])*)\3`, 'g'), groups: [2, 4] },
    { re: new RegExp(String.raw`\bgettext\(\s*${LITERAL}`, 'g'), groups: [2] },
    { re: new RegExp(String.raw`\b_\(\s*${LITERAL}`, 'g'), groups: [2] },
    { re: new RegExp(String.raw`\bt\(\s*${LITERAL}`, 'g'), groups: [2] },
];

/** Undo the escaping of a quoted literal well enough to compare it with a catalog msgid. */
function unescapeLiteral(raw, quote) {
    if (quote === "'") {
        // PHP single quotes: only \\ and \' are escapes; JS treats the rest the same in practice.
        return raw.replace(/\\(.)/g, (m, c) => (c === "'" || c === '\\' ? c : `\\${c}`));
    }
    const map = { n: '\n', t: '\t', r: '\r', '"': '"', '\\': '\\', "'": "'", $: '$' };
    return raw.replace(/\\(.)/g, (m, c) => map[c] ?? c);
}

function literalsInLine(line) {
    const terms = [];
    for (const { re, groups } of CALL_PATTERNS) {
        re.lastIndex = 0;
        let m;
        while ((m = re.exec(line)) !== null) {
            for (const g of groups) {
                if (m[g] !== undefined) terms.push(unescapeLiteral(m[g], m[g - 1]));
            }
        }
    }
    return terms;
}

/**
 * Parse a unified diff (-U0) into the translatable literals it adds and removes.
 * Returns { added: Map<term, [{file, line}]>, removed: Map<term, [{file}]> }.
 */
function extractLiterals(diffText) {
    const added = new Map();
    const removed = new Map();
    let file = null;
    let newLine = 0;
    const collect = (map, term, loc) => {
        const list = map.get(term) ?? [];
        if (list.length < MAX_LOCATIONS && !list.some(l => l.file === loc.file && l.line === loc.line)) list.push(loc);
        map.set(term, list);
    };
    for (const line of diffText.split('\n')) {
        if (line.startsWith('diff --git ')) {
            const m = line.match(/ b\/(.+)$/);
            file = m ? m[1] : null;
        } else if (line.startsWith('@@ ')) {
            const m = line.match(/\+(\d+)/);
            newLine = m ? parseInt(m[1], 10) : 0;
        } else if (line.startsWith('+++') || line.startsWith('---') || line.startsWith('\\')) {
            // headers and "\ No newline" markers carry no literals
        } else if (line.startsWith('+')) {
            if (file) for (const t of literalsInLine(line.slice(1))) collect(added, t, { file, line: newLine });
            newLine++;
        } else if (line.startsWith('-')) {
            if (file) for (const t of literalsInLine(line.slice(1))) collect(removed, t, { file });
        } else if (line !== '') {
            newLine++; // context line (only with -U>0); keep the counter honest
        }
    }
    return { added, removed };
}

/** Every msgid and msgid_plural in a .po file, as a Set (the header's empty msgid excluded). */
function catalogTerms(poText) {
    const terms = new Set();
    for (const e of parsePoEntries(poText)) {
        if (e.msgid) terms.add(e.msgid);
        if (e.msgidPlural) terms.add(e.msgidPlural);
    }
    return terms;
}

/** suspicions()' code test, factored out so the guard can re-run it on a placeholder-free copy. */
function codeLike(term) {
    return /\$[A-Za-z_]|->|::|=>|\(\)|<\?|\bfunction\b|;\s*$/.test(term)
        || (/^[A-Za-z_][\w.]*$/.test(term) && /[a-z][A-Z]|_|\.\w/.test(term));
}

/**
 * The guard itself. `added`/`removed` come from extractLiterals, `catalog` from catalogTerms
 * at the PR's base. Returns [{ term, why: [...], locations: [...] }] sorted by term.
 */
function guardTerms({ added, removed, catalog }) {
    const fresh = [...added.keys()].filter(t => !catalog.has(t)).sort((a, b) => a.localeCompare(b));
    const catalogMap = new Map([...catalog].map(t => [t, {}]));
    const removedCatalog = [...removed.keys()].filter(t => catalog.has(t));
    const flagged = [];
    for (const term of fresh) {
        const why = suspicions(term, catalogMap);
        // suspicions() reads "%1$d" as a $variable; positional and simple placeholders are
        // ordinary gettext, so drop the code flag when nothing code-like remains without them.
        const codeAt = why.indexOf('looks like code or a variable name');
        if (codeAt >= 0 && !codeLike(term.replace(/%\d+\$[a-z]|%[a-z]|\{\{[^}]*\}\}/g, ''))) why.splice(codeAt, 1);
        const n = normalize(term);
        if (n) {
            const twin = fresh.find(other => other !== term && normalize(other) === n);
            if (twin !== undefined) {
                why.push(`near-duplicate of “${twin.length > 80 ? `${twin.slice(0, 79)}…` : twin}”, also added in this PR — keep one wording`);
            }
        }
        for (const old of removedCatalog) {
            if (similar(term, old)) {
                why.push(`similar to “${old.length > 80 ? `${old.slice(0, 79)}…` : old}”, which this PR removes — if it is the same string reworded, keep the existing wording in code so its translations survive`);
                break;
            }
        }
        if (why.length) flagged.push({ term, why, locations: added.get(term) ?? [] });
    }
    return flagged;
}

function cell(s, max = 160) {
    const clipped = s.length > max ? `${s.slice(0, max - 1)}…` : s;
    return clipped.replace(/\\/g, '\\\\').replace(/\|/g, '\\|').replace(/\r?\n/g, '↵').replace(/</g, '&lt;').replace(/`/g, 'ˋ');
}

const MARKER = '<!-- locale-terms-guard -->';

function renderGuardReport(flagged) {
    const lines = [MARKER, '## Locale terms guard', ''];
    if (flagged.length === 0) {
        lines.push('No questionable new terms in this PR. ✅');
        return lines.join('\n') + '\n';
    }
    lines.push(`**${flagged.length} new term${flagged.length === 1 ? '' : 's'} to look at before merge.** `
        + 'These strings are not in the translation catalog yet. Fixing them here is one edit; '
        + 'fixing them after the terms extraction means a cleanup PR and re-translation in 43 languages.');
    lines.push('');
    lines.push('| New term | Found in | Why |');
    lines.push('|---|---|---|');
    for (const f of flagged) {
        const where = f.locations.map(l => `\`${l.file}:${l.line}\``).join('<br>');
        lines.push(`| ${cell(f.term)} | ${where} | ${f.why.map(w => cell(w, 220)).join('; ')} |`);
    }
    lines.push('');
    lines.push('Reuse the existing catalog term when one covers the meaning (see `locale/messages.po`). '
        + 'If a flagged wording is intentional, add the **`locale-terms-ok`** label and say why in the PR description — the check then passes with this comment standing as the record.');
    return lines.join('\n') + '\n';
}

if (require.main === module) {
    const args = process.argv.slice(2);
    const opt = (name, fallback) => {
        const i = args.indexOf(`--${name}`);
        return i >= 0 ? args[i + 1] : fallback;
    };
    const git = (...a) => execFileSync('git', a, { cwd: ROOT, encoding: 'utf8', maxBuffer: 256 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore'] });
    const staged = args.includes('--staged');
    const base = staged ? 'HEAD' : opt('base', process.env.BASE_SHA || 'origin/master');
    const head = opt('head', 'HEAD');
    const diff = staged
        ? git('diff', '-U0', '--no-color', '--cached', '--', ...DIFF_PATHS)
        : git('diff', '-U0', '--no-color', `${base}...${head}`, '--', ...DIFF_PATHS);
    const basePo = git('show', `${base}:${PO_FILE}`);
    const { added, removed } = extractLiterals(diff);
    const flagged = guardTerms({ added, removed, catalog: catalogTerms(basePo) });
    const report = renderGuardReport(flagged);
    if (opt('report')) fs.writeFileSync(opt('report'), report);
    else if (!staged || flagged.length) process.stdout.write(report);
    if (process.env.GITHUB_OUTPUT) fs.appendFileSync(process.env.GITHUB_OUTPUT, `flagged=${flagged.length}\n`);
    console.error(`terms guard: ${added.size} added literals, ${flagged.length} flagged`);
    process.exit(flagged.length ? 1 : 0);
}

module.exports = { extractLiterals, literalsInLine, unescapeLiteral, catalogTerms, guardTerms, renderGuardReport, codeLike, MARKER };
