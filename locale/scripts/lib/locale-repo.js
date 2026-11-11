'use strict';

/**
 * File access for the locale files in the repo (see locale-files.js for the format rules).
 */

const fs = require('fs');
const path = require('path');
const config = require('../locale-config');
const files = require('./locale-files');

function readJSON(file, fallback = null) {
    if (!fs.existsSync(file)) return fallback;
    return JSON.parse(fs.readFileSync(file, 'utf8'));
}

/** Translation targets from src/locale/locales.json: [{ name, code (batch dir), locale, languageCode, skipAudit, scripts }]. */
function targetLocales() {
    return Object.entries(readJSON(config.localesJson, {}))
        .filter(([, entry]) => entry.super_base !== true)
        .map(([name, entry]) => ({
            name,
            code: entry.poEditor,
            locale: entry.locale,
            languageCode: entry.languageCode,
            skipAudit: entry.skip_audit === true,
            scripts: entry.scripts,
        }));
}

/** Filters targetLocales() by a comma list of batch codes or locale codes (de, de_DE, zh-CN). */
function selectLocales(list, arg) {
    if (!arg) return list;
    const wanted = arg.split(',').map((c) => c.trim().toLowerCase()).filter(Boolean);
    const picked = list.filter((l) => wanted.includes(l.code.toLowerCase()) || wanted.includes(l.locale.toLowerCase()));
    const unknown = wanted.filter((w) => !picked.some((l) => l.code.toLowerCase() === w || l.locale.toLowerCase() === w));
    if (unknown.length) throw new Error(`unknown locale(s): ${unknown.join(', ')}`);
    return picked;
}

function paths(target) {
    const dir = path.join(config.srcRoot, 'locale/textdomain', target.locale, 'LC_MESSAGES');
    return {
        json: path.join(config.i18nDir, `${target.locale}.json`),
        po: path.join(dir, 'messages.po'),
        mo: path.join(dir, 'messages.mo'),
        batches: path.join(config.terms.missing, target.code),
    };
}

function loadSource() {
    return files.sourceEntries(fs.readFileSync(config.messagesPo, 'utf8'));
}

/** Everything needed to work on one locale. */
function loadLocale(target, entries) {
    const p = paths(target);
    const translations = readJSON(p.json, {});
    const forms = files.localePluralForms(entries, translations, target.languageCode);
    return {
        target,
        paths: p,
        translations,
        shapes: files.termShapes(entries, forms),
        poText: fs.existsSync(p.po) ? fs.readFileSync(p.po, 'utf8') : '',
        forms,
    };
}

function jsonText(translations) {
    return `${JSON.stringify(translations, null, 2)}\n`;
}

function compileMo(poText) {
    const gettextParser = require('gettext-parser');
    return gettextParser.mo.compile(gettextParser.po.parse(poText));
}

/** Translations a .mo carries, ignoring the header (its charset spelling differs between compilers). */
function moTranslations(buffer) {
    const gettextParser = require('gettext-parser');
    const out = {};
    for (const [ctx, list] of Object.entries(gettextParser.mo.parse(buffer).translations)) {
        for (const [id, e] of Object.entries(list)) if (id !== '') out[`${ctx}\u0004${id}`] = e.msgstr;
    }
    return out;
}

function sameMo(a, b) {
    return JSON.stringify(moTranslations(a)) === JSON.stringify(moTranslations(b));
}

/**
 * Writes JSON, .po and .mo for a locale from `ctx.translations`. Returns what changed; with
 * `dryRun` nothing is written.
 */
function writeLocale(ctx, entries, { dryRun = false } = {}) {
    const { paths: p, translations, shapes, poText, target } = ctx;
    const changes = { json: false, po: false, mo: false, po_added: 0, po_updated: 0, po_removed: 0 };

    const nextJson = jsonText(translations);
    const currentJson = fs.existsSync(p.json) ? fs.readFileSync(p.json, 'utf8') : null;
    changes.json = nextJson !== currentJson;

    const header = files.poHeader(target.languageCode, ctx.forms.length);
    const po = files.renderLocalePo(poText, entries, shapes, translations, header);
    changes.po = po.changed;
    changes.po_added = po.added;
    changes.po_updated = po.updated;
    changes.po_removed = po.removed;

    const mo = compileMo(po.text);
    changes.mo = !fs.existsSync(p.mo) || !sameMo(mo, fs.readFileSync(p.mo));

    if (!dryRun) {
        if (changes.json) fs.writeFileSync(p.json, nextJson, 'utf8');
        if (changes.po) {
            fs.mkdirSync(path.dirname(p.po), { recursive: true });
            fs.writeFileSync(p.po, po.text, 'utf8');
        }
        if (changes.mo) fs.writeFileSync(p.mo, mo);
    }
    return changes;
}

/** Batch files of a locale: [{ file, terms }]. */
function readBatches(dir) {
    if (!fs.existsSync(dir)) return [];
    return fs.readdirSync(dir)
        .filter((f) => f.endsWith('.json'))
        .sort()
        .map((f) => ({ file: path.join(dir, f), terms: readJSON(path.join(dir, f), {}) }));
}

function writeBatch(file, terms) {
    if (Object.keys(terms).length === 0) {
        if (fs.existsSync(file)) fs.unlinkSync(file);
        const dir = path.dirname(file);
        if (fs.existsSync(dir) && fs.readdirSync(dir).length === 0) fs.rmdirSync(dir);
        return;
    }
    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, jsonText(terms), 'utf8');
}

module.exports = {
    readJSON,
    targetLocales,
    selectLocales,
    paths,
    loadSource,
    loadLocale,
    writeLocale,
    compileMo,
    sameMo,
    readBatches,
    writeBatch,
    jsonText,
};
