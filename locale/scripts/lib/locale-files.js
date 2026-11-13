'use strict';

/**
 * Translations live in the repo. For every locale:
 *
 *   src/locale/i18n/<locale>.json                        what agents and the browser read (i18next)
 *   src/locale/textdomain/<locale>/LC_MESSAGES/messages.po  what PHP gettext reads, generated from the JSON
 *   src/locale/textdomain/<locale>/LC_MESSAGES/messages.mo  compiled from the .po by msgfmt
 *
 * locale/messages.po (extracted from the code by `npm run locale:build`) is the list of source terms.
 * A term is missing in a locale when its JSON has no complete translation for it.
 *
 * Term shapes, as messages.po defines them:
 *   plain     msgid only                                JSON "term": "text"
 *   context   msgctxt "one" / "other" entries (i18next)  JSON "term": { "one": "...", "other": "..." }
 *   plural    msgid_plural (PHP ngettext)               JSON "term": { <CLDR forms of the locale> }
 *             .po slots msgstr[0..n] follow the CLDR order of the locale's forms.
 */

const { parsePoEntries, CLDR_PLURAL_FORMS } = require('./poeditor-plurals');

// Every count interpolated in this codebase is a whole number, so a locale needs only the CLDR
// categories Intl.PluralRules can select for integers (Czech "many" is decimal-only, for example).
const PLURAL_SAMPLE_MAX = 1000;

function pluralForms(languageCode) {
    try {
        const rules = new Intl.PluralRules(languageCode);
        const reachable = new Set(['other']);
        for (let n = 0; n <= PLURAL_SAMPLE_MAX; n++) reachable.add(rules.select(n));
        return CLDR_PLURAL_FORMS.filter((form) => reachable.has(form));
    } catch {
        return ['one', 'other'];
    }
}

/** Source entries of locale/messages.po, in file order: [{ msgid, msgctxt?, msgidPlural? }]. */
function sourceEntries(messagesPoText) {
    return parsePoEntries(messagesPoText).filter((e) => e.msgid !== '');
}

/**
 * Plural forms a locale's gettext plural terms use. The JSON files already settle this per locale (the
 * .po slot count follows them), so the forms its existing plural terms use win; a locale with none yet
 * falls back to the CLDR forms of its language.
 */
function localePluralForms(entries, translations, languageCode) {
    const counts = new Map();
    for (const e of entries) {
        if (e.msgidPlural === undefined) continue;
        const value = translations[e.msgid];
        if (!value || typeof value !== 'object' || Array.isArray(value)) continue;
        const forms = CLDR_PLURAL_FORMS.filter((f) => f in value);
        if (forms.length === 0) continue;
        const key = forms.join(',');
        counts.set(key, (counts.get(key) ?? 0) + 1);
    }
    if (counts.size === 0) return pluralForms(languageCode);
    const [best] = [...counts].sort((a, b) => b[1] - a[1])[0];
    return best.split(',');
}

/**
 * Map of JSON key -> { kind: 'plain' | 'context' | 'plural', forms?: string[] }, in source order.
 * `forms` is the context list for context terms and `pluralFormList` for plural terms.
 */
function termShapes(entries, pluralFormList) {
    const shapes = new Map();
    for (const e of entries) {
        if (e.msgidPlural !== undefined) {
            shapes.set(e.msgid, { kind: 'plural', forms: [...pluralFormList] });
        } else if (e.msgctxt !== undefined) {
            const shape = shapes.get(e.msgid);
            if (shape && shape.kind === 'context') {
                if (!shape.forms.includes(e.msgctxt)) shape.forms.push(e.msgctxt);
            } else {
                shapes.set(e.msgid, { kind: 'context', forms: [e.msgctxt] });
            }
        } else if (!shapes.has(e.msgid)) {
            shapes.set(e.msgid, { kind: 'plain' });
        }
    }
    return shapes;
}

const filled = (v) => typeof v === 'string' && v !== '';

function isComplete(value, shape) {
    if (shape.kind === 'plain') return filled(value);
    return value !== null && typeof value === 'object' && !Array.isArray(value) && shape.forms.every((f) => filled(value[f]));
}

/** What is still untranslated, in the batch-file shape: { term: '' } or { term: { form: '' | text } }. */
function findMissing(shapes, translations) {
    const missing = {};
    for (const [term, shape] of shapes) {
        const value = translations[term];
        if (isComplete(value, shape)) continue;
        if (shape.kind === 'plain') {
            missing[term] = '';
        } else {
            const current = value && typeof value === 'object' ? value : {};
            missing[term] = Object.fromEntries(shape.forms.map((f) => [f, filled(current[f]) ? current[f] : '']));
        }
    }
    return missing;
}

/** Terms of `translations` that are no longer in the source (removed or reworded in the code). */
function obsoleteTerms(shapes, translations) {
    return Object.keys(translations).filter((term) => !shapes.has(term));
}

// ---------------------------------------------------------------------------
// .po files
// ---------------------------------------------------------------------------

function unquote(literal) {
    try {
        return JSON.parse(literal);
    } catch {
        return literal.slice(1, -1);
    }
}

/**
 * Splits a .po file into its header and entry blocks, keeping each block's raw text so blocks that do
 * not change are written back byte for byte.
 */
function parsePoBlocks(text) {
    const chunks = text.replace(/\r\n/g, '\n').split(/\n{2,}/).filter((c) => c.trim() !== '');
    const blocks = chunks.map((raw) => {
        const block = { raw, comments: [], msgstrPlural: [] };
        let field = null;
        for (const line of raw.split('\n')) {
            if (line.startsWith('#')) {
                block.comments.push(line);
                continue;
            }
            const m = line.match(/^(msgctxt|msgid_plural|msgid|msgstr(?:\[(\d+)\])?)\s+(".*")$/);
            if (m) {
                const [, keyword, index, literal] = m;
                if (index !== undefined) {
                    field = { list: Number(index) };
                    block.msgstrPlural[Number(index)] = unquote(literal);
                } else {
                    field = { msgctxt: 'msgctxt', msgid: 'msgid', msgid_plural: 'msgidPlural', msgstr: 'msgstr' }[keyword];
                    block[field] = unquote(literal);
                }
                continue;
            }
            if (field !== null && line.startsWith('"')) {
                if (typeof field === 'object') block.msgstrPlural[field.list] += unquote(line);
                else block[field] += unquote(line);
            }
        }
        return block;
    });
    const headerIndex = blocks.findIndex((b) => b.msgid === '' && b.msgctxt === undefined);
    const header = headerIndex === -1 ? null : blocks.splice(headerIndex, 1)[0];
    return { header, blocks };
}

function escapePo(s) {
    return s.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\t/g, '\\t').replace(/\r/g, '\\r');
}

/** One keyword line, split after each \n the way the existing files are written. */
function poString(keyword, value) {
    const parts = value.split('\n');
    if (parts.length === 1) return `${keyword} "${escapePo(value)}"`;
    return parts
        .map((part, i) => `${i === 0 ? `${keyword} ` : ''}"${escapePo(part)}${i < parts.length - 1 ? '\\n' : ''}"`)
        .join('\n');
}

function renderBlock(entry, value, comments) {
    const lines = comments.filter((c) => c !== '#, fuzzy');
    if (lines.length === 0) lines.push('#: ');
    if (entry.msgctxt !== undefined) lines.push(poString('msgctxt', entry.msgctxt));
    lines.push(poString('msgid', entry.msgid));
    if (entry.msgidPlural !== undefined) {
        lines.push(poString('msgid_plural', entry.msgidPlural));
        value.forEach((v, i) => lines.push(poString(`msgstr[${i}]`, v)));
    } else {
        lines.push(poString('msgstr', value));
    }
    return lines.join('\n');
}

const blockKey = (e) => `${e.msgctxt ?? ''}\u0004${e.msgid}`;

/** The .po value an entry should carry for this locale's JSON, or null when it is untranslated. */
function poValue(entry, shapes, translations) {
    const shape = shapes.get(entry.msgid);
    if (!shape) return null;
    const value = translations[entry.msgid];
    if (entry.msgidPlural !== undefined) {
        if (shape.kind !== 'plural' || !isComplete(value, shape)) return null;
        return shape.forms.map((f) => value[f]);
    }
    if (entry.msgctxt !== undefined) {
        if (shape.kind !== 'context' || !value || typeof value !== 'object') return null;
        return filled(value[entry.msgctxt]) ? value[entry.msgctxt] : null;
    }
    return shape.kind === 'plain' && filled(value) ? value : null;
}

// A fuzzy flag left by POEditor is kept on unchanged entries: the .mo includes fuzzy entries either way.
function sameValue(block, entry, value) {
    if (entry.msgidPlural !== undefined) {
        return block.msgidPlural === entry.msgidPlural
            && block.msgstrPlural.length === value.length
            && value.every((v, i) => block.msgstrPlural[i] === v);
    }
    return block.msgidPlural === undefined && block.msgstr === value;
}

/**
 * Rewrites a locale .po so it carries exactly the translated source terms of the locale JSON:
 * unchanged entries keep their text, changed ones are rewritten (dropping a fuzzy flag), entries that
 * left the source or have no translation are dropped, and new translations are appended in source order.
 *
 * Returns { text, changed, added, updated, removed }.
 */
function renderLocalePo(existingText, entries, shapes, translations, headerText) {
    const { header, blocks } = parsePoBlocks(existingText || '');
    const bySource = new Map(entries.map((e) => [blockKey(e), e]));
    const seen = new Set();
    const out = [];
    let updated = 0;
    let removed = 0;
    for (const block of blocks) {
        const key = blockKey(block);
        const entry = bySource.get(key);
        const value = entry ? poValue(entry, shapes, translations) : null;
        if (!entry || value === null || seen.has(key)) {
            removed++;
            continue;
        }
        seen.add(key);
        if (sameValue(block, entry, value)) {
            out.push(block.raw);
        } else {
            out.push(renderBlock(entry, value, block.comments));
            updated++;
        }
    }
    let added = 0;
    for (const entry of entries) {
        const key = blockKey(entry);
        if (seen.has(key)) continue;
        const value = poValue(entry, shapes, translations);
        if (value === null) continue;
        seen.add(key);
        out.push(renderBlock(entry, value, []));
        added++;
    }
    const head = header ? header.raw : headerText;
    const text = `${[head, ...out].join('\n\n')}\n\n`;
    const changed = text !== existingText;
    return { text, changed, added, updated, removed };
}

/** Header for a locale that has no .po yet. */
function poHeader(language, nplurals) {
    return [
        'msgid ""',
        'msgstr ""',
        '"MIME-Version: 1.0\\n"',
        '"Content-Type: text/plain; charset=UTF-8\\n"',
        '"Content-Transfer-Encoding: 8bit\\n"',
        '"Project-Id-Version: ChurchCRM\\n"',
        `"Language: ${language}\\n"`,
        `"Plural-Forms: nplurals=${nplurals}; plural=0;\\n"`,
    ].join('\n');
}

module.exports = {
    pluralForms,
    localePluralForms,
    sourceEntries,
    termShapes,
    isComplete,
    findMissing,
    obsoleteTerms,
    parsePoBlocks,
    poString,
    entryValue: poValue,
    renderLocalePo,
    poHeader,
};
