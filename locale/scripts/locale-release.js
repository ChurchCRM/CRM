#!/usr/bin/env node
'use strict';

/**
 * Release locale step: one command that drives the locale pipeline to "done" before a release.
 *
 *   npm run locale:release            status only: what is running, open, missing, and the next step
 *   npm run locale:release -- --go    run it: extract terms now (skips and cancels the wait), stop for the
 *                                     terms PR review if there is one, otherwise report what is still
 *                                     untranslated in the repo
 *
 * Translations live in the repo (src/locale/i18n, src/locale/textdomain); there is no outside service
 * to sync. Run it from an up-to-date master checkout, as yourself (gh auth). It never merges the terms
 * PR: merge that after review, pull, and run the command again. Exit code 0 = locales are release-ready.
 */

const { execFileSync, spawnSync } = require('child_process');
const path = require('path');

const ROOT = path.join(__dirname, '../..');
const TERMS = 'locale-generate-terms.yml';

const sh = (cmd, args) => execFileSync(cmd, args, { cwd: ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
const gh = (...args) => sh('gh', args);
const json = (...args) => JSON.parse(gh(...args) || '[]');
const sleep = ms => Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, ms);

function activeRuns(workflow) {
    return json('run', 'list', '--workflow', workflow, '--limit', '10', '--json', 'databaseId,status,displayTitle,url,createdAt')
        .filter(r => r.status !== 'completed');
}

function openPrs() {
    const prs = json('pr', 'list', '--state', 'open', '--limit', '50', '--json', 'number,title,url,headRefName');
    return {
        terms: prs.filter(p => p.headRefName.startsWith('locale/terms/') && !p.headRefName.startsWith('locale/terms/missing')),
    };
}

function missingLocales() {
    // Fresh batches from the repo first, so the list matches this checkout
    spawnSync('node', ['locale/scripts/locale-missing.js'], { cwd: ROOT, encoding: 'utf8' });
    const out = spawnSync('node', ['locale/scripts/locale-translate.js', '--list'], { cwd: ROOT, encoding: 'utf8' });
    const text = (out.stdout || '').trim();
    // only the table counts; "All locales are fully translated" means done
    return /Total: \d+ locales/.test(text) ? text : '';
}

function behindMaster() {
    try {
        sh('git', ['fetch', '--quiet', 'origin', 'master']);
        return Number(sh('git', ['rev-list', '--count', 'HEAD..origin/master']));
    } catch {
        return 0;
    }
}

/** Dispatches a workflow on master and waits for the run it started. */
function dispatchAndWatch(workflow, fields = []) {
    const since = new Date(Date.now() - 5000).toISOString();
    gh('workflow', 'run', workflow, '--ref', 'master', ...fields.flatMap(f => ['-f', f]));
    let run;
    for (let i = 0; i < 30 && !run; i++) {
        sleep(4000);
        run = json('run', 'list', '--workflow', workflow, '--event', 'workflow_dispatch', '--limit', '5', '--json', 'databaseId,createdAt,url')
            .find(r => r.createdAt >= since);
    }
    if (!run) throw new Error(`could not find the ${workflow} run just started`);
    console.log(`  ${run.url}`);
    spawnSync('gh', ['run', 'watch', String(run.databaseId), '--interval', '20', '--exit-status'], { cwd: ROOT, stdio: ['ignore', 'ignore', 'inherit'] });
    const done = json('run', 'view', String(run.databaseId), '--json', 'conclusion');
    return done.conclusion;
}

function status() {
    const prs = openPrs();
    const running = activeRuns(TERMS).map(r => ({ ...r, w: TERMS }));
    const missing = missingLocales();
    console.log('Locale release status');
    for (const r of running) console.log(`  running: ${r.displayTitle}  ${r.url}`);
    for (const p of prs.terms) console.log(`  terms PR open: #${p.number} ${p.title}  ${p.url}`);
    console.log(missing ? `  untranslated (this checkout):\n${missing.replace(/^/gm, '    ')}` : '  untranslated: none (this checkout)');
    return { prs, running, missing };
}

function nextStep(s) {
    if (s.prs.terms.length) return `Review and merge ${s.prs.terms[0].url}, then run npm run locale:release -- --go again.`;
    if (s.running.length) return 'Wait for the running locale workflows, then run this again.';
    if (s.missing) return 'Translate the missing terms (/locale-translate --all on a locale/translate/* branch), run npm run locale:apply, and open a PR. After it merges, pull and run this again.';
    return null;
}

function main() {
    const go = process.argv.includes('--go');
    const behind = behindMaster();
    if (behind) console.log(`⚠️  This checkout is ${behind} commits behind origin/master; run git pull --ff-only for an accurate untranslated list.\n`);

    if (go) {
        console.log('1. Extract terms now (cancels any running wait)…');
        const terms = dispatchAndWatch(TERMS);
        if (terms !== 'success') return finish(`Locale: extract terms ended ${terms}.`);
        if (openPrs().terms.length) return finish(null);
        console.log('2. No terms PR: checking the repo for untranslated terms…');
    }
    return finish(null);
}

function finish(error) {
    console.log('');
    const s = status();
    const next = error ?? nextStep(s);
    console.log(next ? `\nNext: ${next}` : '\n✅ Locales are release-ready: no open locale PR, nothing running, nothing untranslated.');
    process.exitCode = next ? 1 : 0;
}

if (require.main === module) main();
