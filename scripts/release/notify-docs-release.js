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
const result = spawnSync('gh', ['api', `repos/${docsRepo}/dispatches`, '--method', 'POST', '--input', '-'], {
    input: payload,
    encoding: 'utf8',
});
if (result.stdout) {
    process.stdout.write(result.stdout);
}
if (result.status !== 0) {
    process.stderr.write(result.stderr || result.error?.message || '');
    process.exit(result.status || 1);
}
console.log('Docs workflow dispatched. It merges only after CI is green and CodeRabbit has approved.');
