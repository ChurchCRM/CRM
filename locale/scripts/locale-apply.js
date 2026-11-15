#!/usr/bin/env node
'use strict';

/**
 * Writes translations into the locale files in the repo, the only place they live:
 * src/locale/i18n/<locale>.json, and from it src/locale/textdomain/<locale>/LC_MESSAGES/messages.po and .mo.
 *
 *   npm run locale:apply                       move every filled term of the batch files
 *                                              (locale/terms/missing/<code>/*.json) into the locale files,
 *                                              then drop it from the batch; empty terms stay
 *   npm run locale:apply -- --locale de,fr     some locales only
 *   npm run locale:apply -- --prune            also drop translations of terms that are no longer in
 *                                              locale/messages.po (the terms workflow runs this)
 *   npm run locale:apply -- --check            write nothing; exit 1 when a locale's .po or .mo does not
 *                                              match its JSON and locale/messages.po
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
    const opts = { locale: null, prune: false, check: false };
    for (let i = 0; i < argv.length; i++) {
        if (argv[i] === '--locale') opts.locale = argv[++i];
        else if (argv[i] === '--prune') opts.prune = true;
        else if (argv[i] === '--check') opts.check = true;
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

/** Moves the filled batch terms of one locale into ctx.translations. */
function applyBatches(ctx, { prune }) {
    const result = { applied: 0, kept: 0, stale: 0, rejected: [] };
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
        repo.writeBatch(file, remaining);
    }
    return result;
}

function main() {
    const opts = parseArgs(process.argv.slice(2));
    const entries = repo.loadSource();
    if (entries.length === 0) {
        console.error('❌ locale/messages.po has no terms; refusing to touch the locale files');
        process.exit(1);
    }

    const outOfSync = [];
    let applied = 0;
    let pruned = 0;
    for (const target of repo.selectLocales(repo.targetLocales(), opts.locale)) {
        const ctx = repo.loadLocale(target, entries);
        const notes = [];

        if (!opts.check) {
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
        }

        const changes = repo.writeLocale(ctx, entries, { dryRun: opts.check });
        const files = ['json', 'po', 'mo'].filter((k) => changes[k]);
        if (opts.check && (changes.po || changes.mo)) outOfSync.push(`${target.locale} (${files.join(', ')})`);
        if (files.length) notes.push(`${opts.check ? 'out of sync' : 'wrote'}: ${files.join(', ')}`);
        if (notes.length) console.log(`${opts.check ? '⚠️ ' : '✅'} ${target.locale.padEnd(6)} ${notes.join(', ')}`);
    }

    if (opts.check) {
        if (outOfSync.length) {
            console.error(`\n❌ ${outOfSync.length} locale(s) have a .po/.mo that does not match their JSON. Run: npm run locale:apply`);
            process.exitCode = 1;
        } else {
            console.log('✅ Every locale .po and .mo matches its JSON.');
        }
        return;
    }
    console.log(`\nApplied ${applied} translations${opts.prune ? `, removed ${pruned} obsolete` : ''}.`);
}

main();
