'use strict';

/**
 * What a terms PR changes, written for the person who reviews it.
 *
 * Compares the msgids in locale/messages.po against a base commit (default HEAD, i.e. master before
 * the extraction) and writes:
 *   --body <file>   PR body: counts, "Needs a look", new and removed terms (kept under GitHub's limit)
 *   --full <file>   the same report without truncation (job summary and artifact)
 * and, under GitHub Actions, these step outputs: added, removed, flagged, has_changes, title.
 *
 * messages.po is built with --no-location, so "Found in" comes from `git grep` on the source tree.
 *
 * Usage: node locale/scripts/lib/locale-terms-diff.js [--base <rev>] [--body f] [--full f] [--date YYYY-MM-DD]
 */

const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const { parsePoEntries } = require('./poeditor-plurals');

const ROOT = path.join(__dirname, '../../..');
const PO_FILE = 'locale/messages.po';
const BODY_LIMIT = 60000; // GitHub rejects PR bodies over 65,536 characters
const LONG_TERM = 200;
const MAX_LOOKUPS = 400;
const SEARCH_PATHS = ['src', 'webpack', ':!src/locale', ':!src/skin/external', ':!src/vendor', ':!*.min.js', ':!*.map'];

/** msgid -> { contexts: Set, plural, comments: [] }, one row per msgid (i18next one/other pairs fold together). */
function termsOf(poText) {
    const terms = new Map();
    const comments = commentsByMsgid(poText);
    for (const e of parsePoEntries(poText)) {
        if (e.msgid === '') continue;
        const t = terms.get(e.msgid) ?? { contexts: new Set(), plural: '', comments: comments.get(e.msgid) ?? [] };
        if (e.msgctxt !== undefined) t.contexts.add(e.msgctxt);
        if (e.msgidPlural) t.plural = e.msgidPlural;
        terms.set(e.msgid, t);
    }
    return terms;
}

/** `#.` extracted comments (plugin help keys) keyed by the msgid that follows them. */
function commentsByMsgid(poText) {
    const map = new Map();
    let pending = [];
    const lines = poText.split(/\r?\n/);
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        if (line.startsWith('#.')) pending.push(line.slice(2).trim());
        else if (line.startsWith('msgid ')) {
            if (pending.length) {
                let id = JSON.parse(line.slice(6));
                for (let j = i + 1; j < lines.length && lines[j].startsWith('"'); j++) id += JSON.parse(lines[j]);
                map.set(id, pending);
            }
            pending = [];
        } else if (line.trim() === '') pending = [];
    }
    return map;
}

function normalize(s) {
    return s.toLowerCase().replace(/[\s\u00a0]+/g, ' ').replace(/[.:!?…,;'"()\-–—]+/g, '').trim();
}

function levenshtein(a, b) {
    if (a === b) return 0;
    let prev = Array.from({ length: b.length + 1 }, (_, i) => i);
    for (let i = 1; i <= a.length; i++) {
        const cur = [i];
        for (let j = 1; j <= b.length; j++) {
            cur[j] = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
        }
        prev = cur;
    }
    return prev[b.length];
}

function similar(a, b) {
    const x = normalize(a);
    const y = normalize(b);
    if (x === y) return true;
    // "PHP version" -> "PHP version (Errors and Full)": the old wording kept and extended
    const [short, long] = x.length <= y.length ? [x, y] : [y, x];
    if (short.length >= 6 && long.startsWith(`${short} `)) return true;
    const longest = Math.max(x.length, y.length);
    if (longest < 8 || Math.abs(x.length - y.length) > longest * 0.2) return false;
    return 1 - levenshtein(x, y) / longest >= 0.85;
}

/** Reasons a new msgid may not belong in the translation project. Empty array = looks fine. */
function suspicions(term, allTerms) {
    const why = [];
    if (term.trim() === '') return ['empty or whitespace only'];
    if (term !== term.trim()) why.push('leading or trailing whitespace');
    if (/ {2,}/.test(term)) why.push('double space');
    if (!/\p{L}/u.test(term)) why.push('no letters (numbers or punctuation only)');
    if (/<\/?[a-z][^>]*>/i.test(term)) why.push('contains HTML tags');
    if (/\$[A-Za-z_]|->|::|=>|\(\)|<\?|\bfunction\b|;\s*$/.test(term)
        || (/^[A-Za-z_][\w.]*$/.test(term) && /[a-z][A-Z]|_|\.\w/.test(term))) {
        why.push('looks like code or a variable name');
    }
    if ((term.match(/\{\{/g) || []).length !== (term.match(/\}\}/g) || []).length) why.push('unbalanced {{ }} placeholder');
    if (term.length > LONG_TERM) why.push(`long (${term.length} characters); hard to translate and to keep in sync`);
    const n = normalize(term);
    const twin = n && [...allTerms.keys()].find(other => other !== term && normalize(other) === n);
    if (twin !== undefined && twin !== false) why.push(`near-duplicate of existing “${clip(twin, 80)}”`);
    return why;
}

function clip(s, max) {
    return s.length > max ? `${s.slice(0, max - 1)}…` : s;
}

/** Markdown-safe single-line cell. */
function cell(s, max = 160) {
    return clip(s, max).replace(/\\/g, '\\\\').replace(/\|/g, '\\|').replace(/\r?\n/g, '↵').replace(/</g, '&lt;').replace(/`/g, 'ˋ');
}

/** Best literal fragment of a msgid to grep for (quotes, escapes and placeholders split it). */
function searchFragment(term) {
    const parts = term.split(/\{\{[^}]*\}\}|%(?:\d+\$)?[sdfu]|["'\\\n\r\t%]/).map(p => p.trim()).filter(Boolean);
    const best = parts.sort((a, b) => b.length - a.length)[0] ?? '';
    return best.length >= 3 ? best.slice(0, 80) : '';
}

function findSource(term, comments, git) {
    const plugin = comments.find(c => c.startsWith('plugin:'));
    if (plugin) return plugin;
    const fragment = searchFragment(term);
    if (!fragment) return '';
    let out = '';
    try {
        out = git('grep', '-n', '-I', '-F', '--max-count=1', '-e', fragment, '--', ...SEARCH_PATHS);
    } catch {
        return ''; // git grep exits 1 when nothing matches
    }
    const hits = out.split('\n').filter(Boolean).map(l => l.split(':').slice(0, 2).join(':'));
    if (hits.length === 0) return '';
    return hits.length === 1 ? hits[0] : `${hits[0]} (+${hits.length - 1} more)`;
}

function diffTerms(basePo, headPo) {
    const base = termsOf(basePo);
    const head = termsOf(headPo);
    const added = [...head.keys()].filter(t => !base.has(t)).sort((a, b) => a.localeCompare(b));
    const removed = [...base.keys()].filter(t => !head.has(t)).sort((a, b) => a.localeCompare(b));
    const flagged = added.map(t => ({ term: t, why: suspicions(t, head) })).filter(f => f.why.length);
    const renames = [];
    const merges = []; // removed near-duplicates of a term that stays: fine, listed for the record
    for (const r of removed) {
        const match = added.find(a => similar(a, r));
        if (match) {
            renames.push({ from: r, to: match });
            continue;
        }
        const kept = normalize(r) && [...head.keys()].find(h => normalize(h) === normalize(r));
        if (kept) merges.push({ from: r, into: kept });
    }
    return { base, head, added, removed, flagged, renames, merges };
}

function termLabel(term, info) {
    const tags = [];
    if (info?.plural) tags.push('plural');
    if (info?.contexts?.size) tags.push([...info.contexts].join('/'));
    return tags.length ? `${cell(term)} _(${tags.join(', ')})_` : cell(term);
}

function section(title, rows, collapseOver = 15) {
    if (rows.length === 0) return '';
    const table = rows.join('\n');
    return rows.length - 2 > collapseOver
        ? `### ${title}\n\n<details><summary>Show ${rows.length - 2}</summary>\n\n${table}\n\n</details>\n`
        : `### ${title}\n\n${table}\n`;
}

function renderReport(d, { sources = new Map(), commits = [], runUrl = '' } = {}) {
    const lines = [];
    const look = d.flagged.length + d.renames.length;
    lines.push(`**+${d.added.length} new, −${d.removed.length} removed** source terms from code `
        + `(messages.po: ${d.base.size} → ${d.head.size} terms). ${look ? `**${look} need a look.**` : 'Nothing flagged.'}`);
    lines.push('');
    lines.push('**Next:** read the strings below. Fix odd ones in the code (not here), or merge this PR. '
        + 'Merging uploads the terms to POEditor right away and downloads translations (`Locale: sync POEditor`). '
        + 'Removed terms lose their translations in every language.');
    lines.push('');
    if (commits.length) {
        lines.push(`<details><summary>Code merges in this batch (${commits.length})</summary>\n`);
        lines.push(commits.slice(0, 50).map(c => `- ${cell(c, 120)}`).join('\n'));
        if (commits.length > 50) lines.push(`- …and ${commits.length - 50} more`);
        lines.push('\n</details>\n');
    }
    if (look) {
        const rows = ['| Term | Why |', '|---|---|'];
        for (const f of d.flagged) rows.push(`| ${cell(f.term)} | ${f.why.join('; ')} |`);
        for (const r of d.renames) {
            rows.push(`| ${cell(r.to)} | replaces removed “${cell(r.from, 80)}”: its translations are lost unless the old term is moved to the new one in POEditor, or the code keeps the old wording |`);
        }
        lines.push(section(`Needs a look (${look})`, rows, 30));
    }
    const addedRows = ['| New term | Found in |', '|---|---|'];
    for (const t of d.added) {
        const src = sources.get(t);
        addedRows.push(`| ${termLabel(t, d.head.get(t))} | ${src ? `\`${cell(src, 100)}\`` : '_not found literally_'} |`);
    }
    lines.push(section(`New terms (${d.added.length})`, d.added.length ? addedRows : []));
    const mergedInto = new Map((d.merges ?? []).map(m => [m.from, m.into]));
    const removedRows = ['| Removed term | Note |', '|---|---|'];
    for (const t of d.removed) {
        const into = mergedInto.get(t);
        removedRows.push(`| ${termLabel(t, d.base.get(t))} | ${into ? `merged into existing “${cell(into, 80)}”` : ''} |`);
    }
    lines.push(section(`Removed terms (${d.removed.length})`, d.removed.length ? removedRows : []));
    if (runUrl) lines.push(`Full list: [run summary and \`locale-terms-report\` artifact](${runUrl}).`);
    return lines.filter(l => l !== undefined).join('\n').replace(/\n{3,}/g, '\n\n').trim() + '\n';
}

function truncateBody(full, runUrl) {
    if (full.length <= BODY_LIMIT) return full;
    const note = `\n\n> ⚠️ Truncated to fit the PR body limit. The full list is in the ${runUrl ? `[run summary](${runUrl})` : 'run summary'} and the \`locale-terms-report\` artifact.\n`;
    let cut = full.slice(0, BODY_LIMIT - note.length - 40);
    cut = cut.slice(0, cut.lastIndexOf('\n'));
    const openDetails = (cut.match(/<details>/g) || []).length - (cut.match(/<\/details>/g) || []).length;
    return cut + '\n' + '\n</details>'.repeat(Math.max(0, openDetails)) + note;
}

function title(d, date) {
    return `locale: new terms from code (+${d.added.length} / -${d.removed.length}) - ${date}`;
}

if (require.main === module) {
    const args = process.argv.slice(2);
    const opt = (name, fallback) => {
        const i = args.indexOf(`--${name}`);
        return i >= 0 ? args[i + 1] : fallback;
    };
    const git = (...a) => execFileSync('git', a, { cwd: ROOT, encoding: 'utf8', maxBuffer: 256 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore'] });
    const baseRev = opt('base', 'HEAD');
    const basePo = git('show', `${baseRev}:${PO_FILE}`);
    const headPo = opt('head') ? git('show', `${opt('head')}:${PO_FILE}`) : fs.readFileSync(path.join(ROOT, PO_FILE), 'utf8');
    const d = diffTerms(basePo, headPo);

    const sources = new Map();
    for (const t of d.added.slice(0, MAX_LOOKUPS)) sources.set(t, findSource(t, d.head.get(t).comments, git));

    // code merges since the last commit that changed messages.po (the burst this PR covers)
    let commits = [];
    try {
        const last = git('log', '-1', '--format=%H', baseRev, '--', PO_FILE).trim();
        if (last) commits = git('log', '--format=%s', `${last}..${baseRev}`, '--', 'src', ':!src/locale').split('\n').filter(Boolean);
    } catch { /* shallow clone */ }

    const runUrl = process.env.GITHUB_RUN_ID
        ? `${process.env.GITHUB_SERVER_URL}/${process.env.GITHUB_REPOSITORY}/actions/runs/${process.env.GITHUB_RUN_ID}`
        : '';
    const full = renderReport(d, { sources, commits });
    const body = truncateBody(renderReport(d, { sources, commits, runUrl }), runUrl);
    const date = opt('date', new Date().toISOString().slice(0, 10));

    if (opt('full')) fs.writeFileSync(opt('full'), full);
    if (opt('body')) fs.writeFileSync(opt('body'), body);
    if (!opt('full') && !opt('body')) process.stdout.write(body);
    if (process.env.GITHUB_OUTPUT) {
        fs.appendFileSync(process.env.GITHUB_OUTPUT, [
            `added=${d.added.length}`, `removed=${d.removed.length}`,
            `flagged=${d.flagged.length + d.renames.length}`,
            `has_changes=${d.added.length + d.removed.length > 0}`,
            `title=${title(d, date)}`, '',
        ].join('\n'));
    }
    console.error(`${title(d, date)}; ${d.flagged.length + d.renames.length} flagged`);
}

module.exports = { diffTerms, suspicions, similar, renderReport, truncateBody, title, searchFragment };
