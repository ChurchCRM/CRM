'use strict';

const SCRIPT_NAME = /^[A-Za-z_]{2,32}$/;

// Script_Extensions, not Script: marks shared between scripts (katakana ー, Arabic tatweel) have Script=Common.
// `name` can come from the command line (locale-add.js --scripts): accept plain script names only and escape
// the value anyway, so it can never change the pattern it is placed in.
function scriptTest(name) {
    if (typeof name !== 'string' || !SCRIPT_NAME.test(name)) return null;
    const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    try {
        return new RegExp(`\\p{Script_Extensions=${escaped}}`, 'u');
    } catch {
        return null;
    }
}

const PLACEHOLDER_RE = /%(\d+\$)?([sdfu])|\{\{\s*(\w+)\s*\}\}/g;

function placeholders(text) {
    const found = [];
    for (const m of String(text).replace(/%%/g, '').matchAll(PLACEHOLDER_RE)) {
        found.push(m[3] ? { name: m[3] } : { index: m[1] ? parseInt(m[1], 10) : null, type: m[2] });
    }
    return found;
}

function placeholderProblems(key, value) {
    const source = placeholders(key);
    const target = placeholders(value);
    const sourceNames = source.filter(p => p.name).map(p => p.name).sort();
    const targetNames = target.filter(p => p.name).map(p => p.name).sort();
    if (sourceNames.join() !== targetNames.join()) {
        return [`{{placeholders}} differ: source [${sourceNames}] vs translation [${targetNames}]`];
    }
    const sourcePrintf = source.filter(p => p.type);
    const targetPrintf = target.filter(p => p.type);
    if (sourcePrintf.length !== targetPrintf.length) {
        return [`printf placeholder count differs: source ${sourcePrintf.length} vs translation ${targetPrintf.length}`];
    }
    const positional = targetPrintf.filter(p => p.index !== null);
    if (positional.length === 0) {
        const mismatch = sourcePrintf.some((p, i) => p.type !== targetPrintf[i].type);
        return mismatch ? ['printf placeholder types are out of order; use positional indexes (%1$s) to reorder'] : [];
    }
    if (positional.length !== targetPrintf.length) {
        return ['mixes positional (%1$s) and plain (%s) placeholders'];
    }
    const seen = new Set();
    for (const p of positional) {
        const expected = sourcePrintf[p.index - 1];
        if (!expected || expected.type !== p.type || seen.has(p.index)) {
            return [`positional placeholder %${p.index}$${p.type} does not match the source`];
        }
        seen.add(p.index);
    }
    return [];
}

/**
 * `scripts` is the locale's "scripts" array from src/locale/locales.json; Latin is always allowed for
 * brand names. A locale without the property is not script-checked.
 */
function scriptProblems(scripts, value) {
    if (!Array.isArray(scripts) || scripts.length === 0) return [];
    const tests = ['Latin', ...scripts].map(scriptTest).filter(Boolean);
    const stray = new Set();
    for (const ch of String(value)) {
        if (/\p{L}/u.test(ch) && !tests.some(t => t.test(ch))) stray.add(ch);
    }
    return stray.size ? [`contains letters outside this locale's scripts (${scripts.join(', ')}): ${[...stray].slice(0, 4).join(' ')}`] : [];
}

// Forms that may spell the number out ("One family", "no families") instead of repeating the placeholder.
const FORMS_WITHOUT_PLACEHOLDER = new Set(['zero', 'one', 'two']);

/**
 * Problems with one incoming translation. Empty means acceptable.
 * Plural objects are checked slot by slot: script for every form, placeholders for the forms that must carry the number.
 */
function validateTranslation(scripts, key, value) {
    if (value && typeof value === 'object') {
        const slots = Object.entries(value);
        const problems = slots.filter(([, v]) => !v).map(([slot]) => `plural form "${slot}" is empty`);
        for (const [slot, v] of slots) {
            if (!v) continue;
            for (const p of scriptProblems(scripts, v)) problems.push(`form "${slot}" ${p}`);
            if (v !== key && !FORMS_WITHOUT_PLACEHOLDER.has(slot)) {
                for (const p of placeholderProblems(key, v)) problems.push(`form "${slot}" ${p}`);
            }
        }
        return problems;
    }
    if (typeof value !== 'string' || value === '') return ['empty'];
    if (value === key) return [];
    return [...placeholderProblems(key, value), ...scriptProblems(scripts, value)];
}

function firstLetterCase(text) {
    const letter = String(text).match(/\p{L}/u)?.[0] ?? '';
    if (letter === '') return 'none';
    return letter === letter.toUpperCase() ? 'upper' : 'lower';
}

function normalizeKey(key) {
    return String(key).trim().replace(/[\s:.!?…]+$/u, '').toLowerCase();
}

/**
 * Zero-token fills for a locale's empty terms.
 * - a term on the locale's english-ok allowlist is filled with itself
 * - a term that differs only in inner case or trailing punctuation from an already translated term reuses that
 *   translation. A different first-letter case means a different context (fragment vs. label), so it is left to the model.
 *
 * @param {Record<string, unknown>} batch  missing-term batch, empty string = untranslated
 * @param {Record<string, unknown>} translated  the locale's existing translations
 * @param {Set<string>} englishOk  allowlisted terms for the locale
 */
function prefill(batch, translated, englishOk = new Set()) {
    const memory = new Map();
    for (const [key, value] of Object.entries(translated)) {
        if (typeof value !== 'string' || value === '') continue;
        const norm = normalizeKey(key);
        const entries = memory.get(norm) ?? [];
        entries.push({ key, value });
        memory.set(norm, entries);
    }

    const filled = {};
    const fromMemory = [];
    const fromAllowlist = [];
    for (const [key, value] of Object.entries(batch)) {
        if (value !== '') continue;
        if (englishOk.has(key)) {
            filled[key] = key;
            fromAllowlist.push(key);
            continue;
        }
        const candidates = (memory.get(normalizeKey(key)) ?? [])
            .filter(c => c.key !== key
                && firstLetterCase(c.key) === firstLetterCase(key)
                && placeholderProblems(key, c.value).length === 0);
        if (candidates.length > 0 && new Set(candidates.map(c => c.value)).size === 1) {
            filled[key] = candidates[0].value;
            fromMemory.push(key);
        }
    }
    return { filled, fromMemory, fromAllowlist };
}

module.exports = { placeholders, validateTranslation, normalizeKey, prefill, scriptTest };
