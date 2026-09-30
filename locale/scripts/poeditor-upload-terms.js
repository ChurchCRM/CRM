#!/usr/bin/env node
/**
 * POEditor Terms Uploader
 *
 * Imports locale/messages.po into POEditor so new source strings exist there before translations are
 * downloaded, and deletes terms that no longer exist in the source. Runs without a model.
 *
 * Reads POEditor's current terms first and does nothing when they already match. After uploading it reads
 * them again and fails unless POEditor matches the file exactly (terms added, stale terms gone). Because the import
 * uses sync_terms, which deletes each removed term and its translations in every language, it refuses
 * to run when the file is empty or would delete more than the limit (2% of the project, at least 25).
 *
 * Usage:
 *   node locale/scripts/poeditor-upload-terms.js                     # upload when POEditor differs
 *   node locale/scripts/poeditor-upload-terms.js --dry-run           # show the plan only
 *   node locale/scripts/poeditor-upload-terms.js --max-deletions 120 # allow a deliberate larger cleanup
 *
 * Requires POEDITOR_TOKEN (from .env or the environment): the same key the download and translation upload use.
 */

const fs = require('fs');
const config = require('./locale-config');
const { planTermSync } = require('./lib/poeditor-terms');

try {
    require('dotenv').config({ quiet: true });
} catch {
    // dotenv is optional; the workflow passes POEDITOR_TOKEN in the environment
}

const POEDITOR_API_BASE = 'https://api.poeditor.com/v2';
const PROJECT_ID = '77079';
const RATE_LIMIT_WAIT_MS = 25_000;
const VERIFY_ATTEMPTS = 3;
const VERIFY_WAIT_MS = 10_000;

function fail(message) {
    console.error(`❌ ${String(message).replace(/[\r\n]+/g, ' ')}`);
    process.exit(1);
}

function parseArgs() {
    const args = process.argv.slice(2);
    const opts = { dryRun: false, maxDeletions: undefined };
    for (let i = 0; i < args.length; i++) {
        if (args[i] === '--dry-run') {
            opts.dryRun = true;
        } else if (args[i] === '--max-deletions') {
            const value = Number(args[++i]);
            if (!Number.isInteger(value) || value < 0) fail('--max-deletions needs a whole number');
            opts.maxDeletions = value;
        } else {
            fail(`Unknown option: ${args[i]}`);
        }
    }
    return opts;
}

async function post(endpoint, form) {
    const response = await fetch(`${POEDITOR_API_BASE}/${endpoint}`, { method: 'POST', body: form });
    const body = await response.json().catch(() => ({}));
    if (!response.ok || body.response?.status !== 'success') {
        const error = new Error(`POEditor ${endpoint}: ${body.response?.message ?? response.statusText}`);
        error.rateLimited = response.status === 429;
        throw error;
    }
    return body.result;
}

async function listRemoteTerms(token) {
    const form = new FormData();
    form.set('api_token', token);
    form.set('id', PROJECT_ID);
    return (await post('terms/list', form)).terms;
}

async function uploadTerms(token, poText) {
    const send = () => {
        const form = new FormData();
        form.set('api_token', token);
        form.set('id', PROJECT_ID);
        form.set('updating', 'terms');
        form.set('type', 'po');
        form.set('sync_terms', '1');
        form.set('file', new Blob([poText], { type: 'text/plain' }), 'messages.po');
        return post('projects/upload', form);
    };
    try {
        return await send();
    } catch (error) {
        if (!error.rateLimited) throw error;
        console.log(`⏳ POEditor rate limit; retrying in ${RATE_LIMIT_WAIT_MS / 1000}s`);
        await new Promise(resolve => setTimeout(resolve, RATE_LIMIT_WAIT_MS));
        return send();
    }
}

async function verifyInSync(token, poText) {
    let plan;
    for (let attempt = 1; attempt <= VERIFY_ATTEMPTS; attempt++) {
        plan = planTermSync(poText, await listRemoteTerms(token));
        if (plan.added.length === 0 && plan.removed.length === 0 && plan.updated.length === 0) return;
        if (attempt < VERIFY_ATTEMPTS) await new Promise(resolve => setTimeout(resolve, VERIFY_WAIT_MS));
    }
    fail(`POEditor still differs from locale/messages.po after the upload: ${plan.added.length} missing, ${plan.removed.length} not deleted, ${plan.updated.length} with a different plural`);
}

function sample(terms) {
    return terms.slice(0, 10).map(t => `     - ${(t.context ? `[${t.context}] ` : '') + t.term.replace(/\s+/g, ' ').slice(0, 80)}`).join('\n');
}

async function main() {
    const opts = parseArgs();
    const token = process.env.POEDITOR_TOKEN;
    if (!token) fail('POEDITOR_TOKEN is not set');

    const poText = fs.readFileSync(config.messagesPo, 'utf8');
    const plan = planTermSync(poText, await listRemoteTerms(token), opts.maxDeletions);

    console.log(`📋 messages.po vs POEditor: ${plan.added.length} to add, ${plan.removed.length} to delete, ${plan.updated.length} plural changes, ${plan.unchanged} unchanged (deletion limit ${plan.limit})`);
    if (plan.added.length) console.log(`   Adding:\n${sample(plan.added)}`);
    if (plan.removed.length) console.log(`   Deleting:\n${sample(plan.removed)}`);

    if (plan.blockedReason) {
        fail(`${plan.blockedReason}. Nothing was uploaded. If the deletion is intended, rerun with --max-deletions ${plan.removed.length}.`);
    }
    if (plan.added.length === 0 && plan.removed.length === 0 && plan.updated.length === 0) {
        console.log('✅ POEditor terms already match locale/messages.po; nothing to upload');
        return;
    }
    if (opts.dryRun) {
        console.log('🔍 Dry run: nothing uploaded');
        return;
    }

    const result = (await uploadTerms(token, poText)).terms ?? {};
    console.log(`✅ Uploaded terms to POEditor: parsed ${result.parsed ?? '?'}, added ${result.added ?? '?'}, deleted ${result.deleted ?? '?'}`);

    await verifyInSync(token, poText);
    console.log('✅ Verified: POEditor terms now match locale/messages.po exactly');
}

main().catch(error => fail(error.message));
