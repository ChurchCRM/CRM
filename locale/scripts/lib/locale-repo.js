'use strict';

/**
 * File access for the locale files in the repo (see locale-files.js for the format rules).
 */

const fs = require('fs');
const path = require('path');
const config = require('../locale-config');
const files = require('./locale-files');

function readJSON(file, fallback = null) {
    let text;
    try {
        text = fs.readFileSync(file, 'utf8');
    } catch (err) {
        if (err.code === 'ENOENT') return fallback;
        throw err;
    }
    return JSON.parse(text);
}

/** Translation targets from src/locale/locales.json: [{ name, code (batch dir), locale, languageCode, skipAudit, scripts }]. */
function targetLocales() {
    return Object.entries(readJSON(config.localesJson, {}))
        .filter(([, entry]) => entry.super_base !== true)
        .map(([name, entry]) => ({
            name,
            code: entry.localeCode,
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
        poText: readIfPresent(p.po, 'utf8') ?? '',
        forms,
    };
}

/** File contents, or null when the file does not exist (read once: no exists-then-read race). */
function readIfPresent(file, encoding) {
    try {
        return fs.readFileSync(file, encoding);
    } catch (err) {
        if (err.code === 'ENOENT') return null;
        throw err;
    }
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

/** Writes the locale JSON (the source of truth) from `ctx.translations`. Returns whether it changed. */
function writeJson(ctx) {
    const next = jsonText(ctx.translations);
    const changed = next !== readIfPresent(ctx.paths.json, 'utf8');
    if (changed) fs.writeFileSync(ctx.paths.json, next, 'utf8');
    return changed;
}

/**
 * Writes the gettext .po/.mo PHP reads from `ctx.translations`. They are generated, gitignored
 * files: this runs at build time (npm run locale:compile) and never touches the JSON.
 */
function writeCatalogs(ctx, entries) {
    const { paths: p, translations, shapes, poText, target } = ctx;
    const header = files.poHeader(target.languageCode, ctx.forms.length);
    const po = files.renderLocalePo(poText, entries, shapes, translations, header);
    const mo = compileMo(po.text);
    const currentMo = readIfPresent(p.mo);

    fs.mkdirSync(path.dirname(p.po), { recursive: true });
    if (po.changed) fs.writeFileSync(p.po, po.text, 'utf8');
    if (currentMo === null || !sameMo(mo, currentMo)) fs.writeFileSync(p.mo, mo);
}

function readDirIfPresent(dir) {
    try {
        return fs.readdirSync(dir);
    } catch (err) {
        if (err.code === 'ENOENT') return null;
        throw err;
    }
}

/** Batch files of a locale: [{ file, terms }]. */
function readBatches(dir) {
    return (readDirIfPresent(dir) ?? [])
        .filter((f) => f.endsWith('.json'))
        .sort()
        .map((f) => ({ file: path.join(dir, f), terms: readJSON(path.join(dir, f), {}) }));
}

function writeBatch(file, terms) {
    if (Object.keys(terms).length === 0) {
        fs.rmSync(file, { force: true });
        const dir = path.dirname(file);
        if ((readDirIfPresent(dir) ?? ['keep']).length === 0) fs.rmdirSync(dir);
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
    writeJson,
    writeCatalogs,
    compileMo,
    sameMo,
    readBatches,
    writeBatch,
    jsonText,
};
