const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const { spawnSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const root = path.join(__dirname, '..', '..');
const script = path.join(root, 'scripts/release/notify-docs-release.js');

describe('notify-docs-release', () => {
    it('exits nonzero when gh rejects the dispatch', () => {
        const bin = fs.mkdtempSync(path.join(os.tmpdir(), 'fake-gh-'));
        fs.writeFileSync(path.join(bin, 'gh'), '#!/bin/sh\necho "dispatch refused" >&2\nexit 1\n');
        fs.chmodSync(path.join(bin, 'gh'), 0o755);
        const result = spawnSync(process.execPath, [script], {
            encoding: 'utf8',
            env: {
                ...process.env,
                PATH: `${bin}${path.delimiter}${process.env.PATH}`,
                VERSION: '1.2.3',
                GH_TOKEN: 'test-token',
                DOCS_REPO: 'ChurchCRM/docs.churchcrm.io',
            },
        });
        assert.notEqual(result.status, 0);
        assert.match(result.stderr, /dispatch refused/);
    });

    it('lets a failed dispatch fail the publish and bookkeeping jobs', () => {
        for (const file of ['.github/workflows/release-publish.yml', '.github/workflows/release-bookkeeping.yml']) {
            const text = fs.readFileSync(path.join(root, file), 'utf8');
            const job = text.slice(text.indexOf('notify_docs:') !== -1 ? text.indexOf('notify_docs:') : text.indexOf('docs-release:'));
            const step = job.slice(0, job.indexOf('node scripts/release/notify-docs-release.js'));
            assert.equal(step.includes('continue-on-error'), false, file);
        }
        const bookkeeping = fs.readFileSync(path.join(root, '.github/workflows/release-bookkeeping.yml'), 'utf8');
        assert.match(bookkeeping, /workflow_dispatch' && github\.sha \|\| 'master'/);
    });
});
