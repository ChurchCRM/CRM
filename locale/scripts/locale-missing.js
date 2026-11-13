#!/usr/bin/env node
'use strict';

/**
 * Writes the untranslated terms of every locale to locale/terms/missing/<code>/<code>-<n>.json, read
 * straight from the repo: a term is missing when locale/messages.po has it and the locale JSON has no
 * complete translation for it. The batch files are the checked-in queue: the terms workflow runs this after
 * every extraction, /locale-translate fills them and `npm run locale:apply` writes them into the locale
 * files and removes the applied terms.
 *
 *   npm run locale:missing                          every locale
 *   npm run locale:missing -- --locale de,fr        some locales
 *   npm run locale:missing -- --term "New text"     also queue a term that is not in messages.po yet
 *                                                   (a reworded string in the PR you are working on);
 *                                                   repeat --term for more
 *
 * Locales flagged skip_audit (the English variants) only override a few terms by hand, so they get
 * no generated batches; their hand-written batches are left alone.
 */

const fs = require('fs');
const path = require('path');
const config = require('./locale-config');
const repo = require('./lib/locale-repo');
const { findMissing } = require('./lib/locale-files');

const BATCH_SIZE = config.settings.missingTermsBatchSize;

function parseArgs(argv) {
    const opts = { locale: null, terms: [] };
    for (let i = 0; i < argv.length; i++) {
        if (argv[i] === '--locale') opts.locale = argv[++i];
        else if (argv[i] === '--term') opts.terms.push(argv[++i]);
        else if (argv[i] === '--help' || argv[i] === '-h') {
            console.log(fs.readFileSync(__filename, 'utf8').match(/\/\*\*([\s\S]*?)\*\//)[1].replace(/^ \* ?/gm, ''));
            process.exit(0);
        } else {
            console.error(`Unknown argument: ${argv[i]}`);
            process.exit(1);
        }
    }
    if (opts.terms.some((t) => !t)) {
        console.error('--term needs a value');
        process.exit(1);
    }
    return opts;
}

const isFilled = (v) => (typeof v === 'string' ? v !== '' : v && typeof v === 'object' && Object.values(v).some((s) => s !== ''));

/** Filled values already in the batch files (work not yet applied, or a hand-written fix): kept. */
function pendingWork(dir) {
    const pending = {};
    for (const { terms } of repo.readBatches(dir)) {
        for (const [term, value] of Object.entries(terms)) if (isFilled(value)) pending[term] = value;
    }
    return pending;
}

function writeBatches(dir, code, missing) {
    for (const old of repo.readBatches(dir)) fs.unlinkSync(old.file);
    const entries = Object.entries(missing);
    for (let i = 0; i < entries.length; i += BATCH_SIZE) {
        const n = i / BATCH_SIZE + 1;
        repo.writeBatch(path.join(dir, `${code}-${n}.json`), Object.fromEntries(entries.slice(i, i + BATCH_SIZE)));
    }
    if (entries.length === 0 && fs.existsSync(dir) && fs.readdirSync(dir).length === 0) fs.rmdirSync(dir);
}

function main() {
    const opts = parseArgs(process.argv.slice(2));
    const entries = repo.loadSource();
    const known = new Set(entries.map((e) => e.msgid));
    for (const term of opts.terms) {
        if (!known.has(term)) entries.push({ msgid: term });
    }

    let total = 0;
    let locales = 0;
    for (const target of repo.selectLocales(repo.targetLocales(), opts.locale)) {
        if (target.skipAudit) continue;
        const ctx = repo.loadLocale(target, entries);
        const missing = findMissing(ctx.shapes, ctx.translations);
        for (const term of opts.terms) {
            if (!(term in missing)) missing[term] = '';
        }
        const pending = pendingWork(ctx.paths.batches);
        for (const [term, value] of Object.entries(pending)) {
            if (typeof missing[term] === 'object' && typeof value === 'object') missing[term] = { ...missing[term], ...value };
            else missing[term] = value;
        }
        const count = Object.values(missing).filter((v) => !isFilled(v) || (typeof v === 'object' && Object.values(v).some((s) => s === ''))).length;
        writeBatches(ctx.paths.batches, target.code, missing);
        const kept = Object.keys(pending).length;
        if (count > 0 || kept > 0) {
            locales += count > 0 ? 1 : 0;
            total += count;
            console.log(`📄 ${target.code.padEnd(6)} ${String(count).padStart(5)} missing${kept ? `, ${kept} filled and not applied yet` : ''}`);
        }
    }
    console.log(total === 0
        ? '✅ Nothing to translate: every locale has every term.'
        : `\n${total} missing terms in ${locales} locales → ${path.relative(config.projectRoot, config.terms.missing)}/\nNext: /locale-translate, then npm run locale:apply`);
}

main();
