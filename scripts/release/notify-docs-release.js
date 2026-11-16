#!/usr/bin/env node

/**
 * Ask docs.churchcrm.io to stage or publish one ChurchCRM release.
 * GH_TOKEN must be able to send repository_dispatch to that repo (Contents: write).
 * VERSION is the release tag. MERGE=true publishes; MERGE=false only stages the pin pull request.
 */

const { spawnSync } = require('child_process');

const version = process.env.VERSION;
const docsRepo = process.env.DOCS_REPO || 'ChurchCRM/docs.churchcrm.io';
const merge = process.env.MERGE === 'true';

if (!version) {
    console.error('VERSION is required');
    process.exit(1);
}

if (!process.env.GH_TOKEN) {
    console.log(`::warning::DOCS_RELEASE_TOKEN is not set. The docs site was not updated. The token needs Contents: write on ${docsRepo}.`);
    process.exit(0);
}

if (!/^[0-9]+\.[0-9]+\.[0-9]+$/.test(version)) {
    console.error(`::error::VERSION must be a stable x.y.z tag. Found: ${version}`);
    process.exit(1);
}

const payload = JSON.stringify({
    event_type: 'crm-released',
    client_payload: {
        version,
        next_version: process.env.NEXT_VERSION || '',
        merge,
    },
});

console.log(`Dispatching crm-released for ${version} (merge=${merge})`);
// One retry: on 2026-11-12 (7.8.4) this single POST failed and no dispatch
// reached the docs repo from either sender that day, so the docs published a
// day late via a manual re-dispatch. A transient API failure should not be
// able to do that again.
let result;
for (let attempt = 1; attempt <= 2; attempt++) {
    result = spawnSync('gh', ['api', `repos/${docsRepo}/dispatches`, '--method', 'POST', '--input', '-'], {
        input: payload,
        encoding: 'utf8',
    });
    if (result.status === 0) break;
    if (attempt < 2) {
        console.log('Dispatch attempt 1 failed; retrying in 20 seconds.');
        spawnSync('sleep', ['20']);
    }
}
if (result.stdout) {
    process.stdout.write(result.stdout);
}
if (result.status !== 0) {
    process.stderr.write(result.stderr || result.error?.message || '');
    console.error('::error::Docs dispatch failed after 2 attempts. First suspect: the DOCS_RELEASE_TOKEN secret — it needs Contents: write on ' + docsRepo + ' and must not be expired or rotated. The docs repo also runs a daily backup publish (06:30 UTC), so the docs will still land; re-run this job after fixing the token to publish immediately.');
    process.exit(result.status || 1);
}
console.log('Docs workflow dispatched. It merges only after CI is green and CodeRabbit has approved.');
