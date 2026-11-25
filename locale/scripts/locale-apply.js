#!/usr/bin/env node
'use strict';

/**
 * Writes translations into the locale JSON, the only place they are committed:
 * src/locale/i18n/<locale>.json. The PHP gettext catalogs (.po/.mo) are never written here: they are
 * compiled from the JSON at build time by npm run locale:compile.
 *
 *   npm run locale:apply                       move every filled term of the batch files
 *                                              (locale/terms/missing/<code>/*.json) into the locale JSON,
 *                                              then drop it from the batch; empty terms stay
 *   npm run locale:apply -- --locale de,fr     some locales only
 *   npm run locale:apply -- --prune            also drop translations of terms that are no longer in
 *                                              locale/messages.po (the terms workflow runs this)
 *
 * Every incoming value is checked (placeholders, script, plural forms); a rejected value stays in its
 * batch file with the reason printed, and the exit code is 1.
 */

const fs = require('fs');
const path = require('path');
const config = require('./locale-config');
const repo = require('./lib/locale-repo');
const { obsoleteTerms } = require('./lib/locale-files');
const { validateTranslation } = require('./lib/translation-checks');

function parseArgs(argv) {
    const opts = { locale: null, prune: false };
    for (let i = 0; i < argv.length; i++) {
        if (argv[i] === '--locale') opts.locale = argv[++i];
        else if (argv[i] === '--prune') opts.prune = true;
        else if (argv[i] === '--help' || argv[i] === '-h') {
            console.log(fs.readFileSync(__filename, 'utf8').match(/\/\*\*([\s\S]*?)\*\//)[1].replace(/^ \* ?/gm, ''));
            process.exit(0);
        } else {
            console.error(`Unknown argument: ${argv[i]}`);
            process.exit(1);
        }
    }
    return opts;
}

const isObject = (v) => v !== null && typeof v === 'object' && !Array.isArray(v);

function isEmpty(value) {
    if (typeof value === 'string') return value === '';
    return isObject(value) && Object.values(value).some((v) => v === '');
}

function shapeProblems(shape, value) {
    if (!shape) return typeof value === 'string' ? [] : ['this term is not in locale/messages.po yet; send a string'];
    if (shape.kind === 'plain') return typeof value === 'string' ? [] : ['this term has no plural forms; send a string'];
    if (!isObject(value)) return ['this term has plural forms; send an object with every form'];
    const want = [...shape.forms].sort().join();
    return Object.keys(value).sort().join() === want ? [] : [`plural forms must be exactly: ${want}`];
}

/**
 * Moves the filled batch terms of one locale into ctx.translations (memory only).
 * The batch files on disk are NOT touched here: they are returned as
 * result.pendingWrites and flushed by flushPendingWrites() only after the
 * locale files (JSON, .po, .mo) have been written successfully, so a failure
 * while writing or compiling a locale can never consume filled batches.
 */
function applyBatches(ctx, { prune }) {
    const result = { applied: 0, kept: 0, stale: 0, rejected: [], pendingWrites: [] };
    for (const { file, terms } of repo.readBatches(ctx.paths.batches)) {
        const remaining = {};
        for (const [term, value] of Object.entries(terms)) {
            const shape = ctx.shapes.get(term);
            if (prune && !shape) {
                result.stale++;
                continue;
            }
            if (isEmpty(value) || value === null) {
                remaining[term] = value;
                result.kept++;
                continue;
            }
            const problems = [...shapeProblems(shape, value), ...validateTranslation(ctx.target.scripts, term, value)];
            if (problems.length) {
                remaining[term] = value;
                result.rejected.push({ file, term, problems });
                continue;
            }
            ctx.translations[term] = value;
            result.applied++;
        }
        result.pendingWrites.push({ file, remaining });
    }
    return result;
}

/** Writes the batch files collected by applyBatches. Call only after the locale files are safely written. */
function flushPendingWrites(pendingWrites) {
    for (const { file, remaining } of pendingWrites) repo.writeBatch(file, remaining);
}

function main() {
    const opts = parseArgs(process.argv.slice(2));
    const entries = repo.loadSource();
    if (entries.length === 0) {
        console.error('❌ locale/messages.po has no terms; refusing to touch the locale files');
        process.exit(1);
    }

    let applied = 0;
    let pruned = 0;
    for (const target of repo.selectLocales(repo.targetLocales(), opts.locale)) {
        const ctx = repo.loadLocale(target, entries);
        const notes = [];

        const r = applyBatches(ctx, opts);
        applied += r.applied;
        if (r.applied) notes.push(`${r.applied} applied`);
        if (r.kept) notes.push(`${r.kept} still empty`);
        if (r.stale) notes.push(`${r.stale} stale batch terms dropped`);
        for (const { file, term, problems } of r.rejected) {
            console.error(`❌ ${target.code}: rejected "${term.slice(0, 70)}" (${path.relative(config.projectRoot, file)}): ${problems.join('; ')}`);
            process.exitCode = 1;
        }
        if (opts.prune) {
            const obsolete = obsoleteTerms(ctx.shapes, ctx.translations);
            for (const term of obsolete) delete ctx.translations[term];
            pruned += obsolete.length;
            if (obsolete.length) notes.push(`${obsolete.length} obsolete removed`);
        }

        if (repo.writeJson(ctx)) notes.push('wrote: json');
        // The JSON is written; only now may the batches be consumed. If writeJson
        // threw, the filled batches are still on disk for the next run.
        flushPendingWrites(r.pendingWrites);
        if (notes.length) console.log(`✅ ${target.locale.padEnd(6)} ${notes.join(', ')}`);
    }

    console.log(`\nApplied ${applied} translations${opts.prune ? `, removed ${pruned} obsolete` : ''}.`);
}

if (require.main === module) main();

module.exports = { applyBatches, flushPendingWrites };
