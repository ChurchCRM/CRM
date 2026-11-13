/**
 * Plural handling for locale terms.
 *
 * ChurchCRM has two kinds of plural term:
 *
 *   gettext plurals (PHP ngettext, `msgid_plural` in locale/messages.po)
 *     One term with one slot per plural form of the target language:
 *     { "term": { "one": "...", "few": "...", "other": "..." } }
 *
 *   context plurals (i18next `{{count}}` keys, `msgctxt "one"` / `msgctxt "other"`)
 *     One entry per context, kept apart from the gettext plurals.
 *
 * The missing-terms batch files show both kinds with the same shape
 * ({ term: { form: text } }), so messages.po decides which shape each
 * term gets.
 */

const CLDR_PLURAL_FORMS = ['zero', 'one', 'two', 'few', 'many', 'other'];

function unquotePo(literal) {
    try {
        return JSON.parse(literal);
    } catch {
        return literal.slice(1, -1);
    }
}

/**
 * Minimal PO reader: returns [{ msgctxt, msgid, msgidPlural }] for every entry.
 * Handles multi-line strings (`msgid ""` followed by continuation lines).
 */
function parsePoEntries(text) {
    const entries = [];
    let entry = {};
    let field = null;

    const flush = () => {
        if (entry.msgid !== undefined) entries.push(entry);
        entry = {};
        field = null;
    };

    for (const rawLine of text.split(/\r?\n/)) {
        const line = rawLine.trim();
        if (line === '') {
            flush();
            continue;
        }
        if (line.startsWith('#')) continue;

        const match = line.match(/^(msgctxt|msgid_plural|msgid|msgstr(?:\[\d+\])?)\s+(".*")$/);
        if (match) {
            const [, keyword, literal] = match;
            if (keyword === 'msgid' && entry.msgid !== undefined) flush();
            field = { msgctxt: 'msgctxt', msgid: 'msgid', msgid_plural: 'msgidPlural' }[keyword] ?? null;
            if (field) entry[field] = unquotePo(literal);
            continue;
        }
        if (field && line.startsWith('"')) {
            entry[field] += unquotePo(line);
        }
    }
    flush();
    return entries;
}

/**
 * Classifies source terms from locale/messages.po.
 * Returns { gettextPlurals: Map<msgid, msgid_plural>, contextForms: Map<msgid, Set<context>> }.
 */
function loadSourceTermKinds(messagesPoText) {
    const gettextPlurals = new Map();
    const contextForms = new Map();

    for (const { msgctxt, msgid, msgidPlural } of parsePoEntries(messagesPoText)) {
        if (!msgid) continue;
        if (msgidPlural !== undefined) gettextPlurals.set(msgid, msgidPlural);
        if (msgctxt !== undefined && CLDR_PLURAL_FORMS.includes(msgctxt)) {
            if (!contextForms.has(msgid)) contextForms.set(msgid, new Set());
            contextForms.get(msgid).add(msgctxt);
        }
    }
    return { gettextPlurals, contextForms };
}

function isPluralObject(value) {
    return (
        value !== null &&
        typeof value === 'object' &&
        !Array.isArray(value) &&
        Object.keys(value).length > 0 &&
        Object.keys(value).every((k) => CLDR_PLURAL_FORMS.includes(k))
    );
}

/**
 * Undo the old pipe-joining of plural forms, which was stored whole in the
 * first plural slot: { one: "A|B|C", few: "", other: "" }.
 *
 * - Parts match the slot count → re-slot in CLDR order (the order they were joined in).
 * - Parts don't match → blank every slot, so the term is translated again
 *   instead of being skipped forever as incomplete.
 * Anything else is returned unchanged.
 */
function repairJoinedPlural(value) {
    if (!isPluralObject(value)) return value;

    const slots = CLDR_PLURAL_FORMS.filter((form) => form in value);
    if (slots.length < 2) return value;

    const filled = slots.filter((form) => typeof value[form] === 'string' && value[form].trim() !== '');
    if (filled.length !== 1 || !value[filled[0]].includes('|')) return value;

    const parts = value[filled[0]].split('|').map((part) => part.trim());
    const repaired = {};
    if (parts.length === slots.length && parts.every((part) => part !== '')) {
        slots.forEach((form, i) => { repaired[form] = parts[i]; });
    } else {
        for (const form of slots) repaired[form] = '';
    }
    return repaired;
}

/**
 * Builds the key_value_json object for projects/upload.
 * Only pass terms whose plural objects are complete (see hasIncompleteForms).
 *
 * Returns { payload, pluralEntries, skipped }: payload is the plain-string
 * key_value_json, pluralEntries are every plural for buildPluralPo, and skipped lists plural terms that could not
 * be matched to messages.po, or that carried a form messages.po does not define.
 */
function buildPluralPayload(terms, { gettextPlurals, contextForms }) {
    const payload = {};
    const pluralEntries = [];
    const skipped = [];

    for (const [term, value] of Object.entries(terms)) {
        if (typeof value === 'string') {
            if (value.trim() !== '') payload[term] = value;
            continue;
        }
        if (!isPluralObject(value)) {
            skipped.push(term);
            continue;
        }

        if (gettextPlurals.has(term)) {
            pluralEntries.push({ msgid: term, msgidPlural: gettextPlurals.get(term), forms: { ...value } });
            continue;
        }

        const contexts = contextForms.get(term);
        if (!contexts) {
            skipped.push(term);
            continue;
        }
        const forms = Object.keys(value);
        if (!forms.every((form) => contexts.has(form))) {
            skipped.push(term);
            continue;
        }
        for (const form of forms) {
            pluralEntries.push({ msgctxt: form, msgid: term, forms: { other: value[form] } });
        }
    }

    return { payload, pluralEntries, skipped };
}

/**
 * PO file for pluralEntries, with msgctxt for {{count}} pairs and
 * msgid_plural/msgstr[n] for gettext plurals. Forms are in CLDR order,
 * which is the msgstr index order.
 */
function buildPluralPo(language, entries) {
    const q = JSON.stringify;
    const maxForms = Math.max(1, ...entries.map(({ msgidPlural, forms }) => (msgidPlural ? Object.keys(forms).length : 1)));
    const header = `msgid ""\nmsgstr ""\n"Content-Type: text/plain; charset=UTF-8\\n"\n"Language: ${language}\\n"\n"Plural-Forms: nplurals=${maxForms}; plural=0;\\n"\n\n`;
    return header + entries.map(({ msgctxt, msgid, msgidPlural, forms }) => {
        const lines = [];
        if (msgctxt !== undefined) lines.push(`msgctxt ${q(msgctxt)}`);
        lines.push(`msgid ${q(msgid)}`);
        if (msgidPlural === undefined) {
            lines.push(`msgstr ${q(Object.values(forms)[0])}`);
        } else {
            lines.push(`msgid_plural ${q(msgidPlural)}`);
            CLDR_PLURAL_FORMS.filter((f) => f in forms).forEach((f, i) => lines.push(`msgstr[${i}] ${q(forms[f])}`));
        }
        return lines.join('\n') + '\n';
    }).join('\n');
}

module.exports = {
    CLDR_PLURAL_FORMS,
    parsePoEntries,
    loadSourceTermKinds,
    repairJoinedPlural,
    buildPluralPayload,
    buildPluralPo,
};
