const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { test } = require('node:test');

const workflow = fs.readFileSync(path.join(__dirname, '../.github/workflows/build-test-nightly.yml'), 'utf8');
const summaryJob = workflow.split('\n  nightly-summary:')[1];
const summaryScript = summaryJob.match(/      run: \|\n([\s\S]*)/)[1].split('\n').map((line) => line.replace(/^ {8}/, '')).join('\n');

function summarize(t, result, changed = true) {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'churchcrm-summary-'));
    t.after(() => fs.rmSync(directory, { recursive: true, force: true }));
    const report = path.join(directory, 'summary.md');
    const script = summaryScript
        .replace(/\$\{\{\s*needs\.changes\.outputs\.run\s*}}/g, String(changed))
        .replace(/\$\{\{\s*needs\.([\w-]+)\.result\s*}}/g, (_, job) => job === 'php-regressions' ? result : 'success');
    const execution = spawnSync('bash', ['-e', '-c', script], {
        encoding: 'utf8',
        env: { ...process.env, GITHUB_STEP_SUMMARY: report, CHANGES_REASON: 'No testable changes' },
    });
    return { ...execution, report: fs.existsSync(report) ? fs.readFileSync(report, 'utf8') : '' };
}

test('nightly summary waits for PHP regressions', () => {
    assert.match(summaryJob.split('    if:')[0], /^      - php-regressions$/m);
});

test('nightly summary passes when PHP regressions succeed', (t) => {
    const result = summarize(t, 'success');
    assert.equal(result.status, 0, result.stderr);
    assert.match(result.report, /Overall:.*PASSED/);
});

for (const conclusion of ['failure', 'cancelled', 'skipped']) {
    test(`nightly summary fails when PHP regressions are ${conclusion}`, (t) => {
        const result = summarize(t, conclusion);
        assert.equal(result.status, 1, result.stderr);
        assert.match(result.report, /Overall:.*FAILED/);
    });
}

test('an unchanged nightly run can skip the PHP suite', (t) => {
    const result = summarize(t, 'skipped', false);
    assert.equal(result.status, 0, result.stderr);
    assert.match(result.report, /NOTHING TO TEST/);
});
