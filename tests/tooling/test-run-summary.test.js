const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { spawnSync } = require('node:child_process');
const { test } = require('node:test');
const { check, render } = require('../../scripts/ci/test-run-summary');

const groups = ['configure', 'lint', 'build', 'test-api-root', 'test-admin-ui-root', 'test-staff-ui-root', 'test-new-system'];
const successful = () => Object.fromEntries(groups.map(name => [name, { result: 'success' }]));

test('all required test groups must pass', () => {
    assert.equal(check(successful(), false).ok, true);
    for (const name of groups) {
        for (const result of ['failure', 'cancelled', 'skipped', undefined]) {
            const needs = successful();
            needs[name] = { result };
            assert.equal(check(needs, false).ok, false, `${name}: ${result}`);
            assert.match(check(needs, false).summary, new RegExp(`${name} \\(${result ?? 'missing'}\\)`));
        }
    }
});

test('locale skip requires a successful configure and every test/build group skipped', () => {
    const needs = Object.fromEntries(groups.map(name => [name, { result: name === 'configure' ? 'success' : 'skipped' }]));
    assert.equal(check(needs, true).ok, true);
    assert.equal(check(needs, false).ok, false);
    needs.build.result = 'failure';
    assert.equal(check(needs, true).ok, false);
    needs.build.result = 'skipped';
    needs.configure.result = 'cancelled';
    assert.equal(check(needs, true).ok, false);
});

test('report links failures, distinguishes skipped jobs, and ranks completed steps', () => {
    const report = render([
        { name: 'API / root / shard 0', html_url: 'https://example.test/jobs/1', conclusion: 'failure', started_at: '2026-11-13T00:00:00Z', completed_at: '2026-11-13T00:04:05Z', steps: [
            { name: 'Install', conclusion: 'success', started_at: '2026-11-13T00:00:00Z', completed_at: '2026-11-13T00:00:10Z' },
            { name: 'Cypress | <failed>\nassertion', conclusion: 'failure' },
        ] },
        { name: 'build', html_url: 'https://example.test/jobs/2', conclusion: 'success', steps: [
            { name: 'Build Docker images', conclusion: 'success', started_at: '2026-11-13T00:00:00Z', completed_at: '2026-11-13T00:03:00Z' },
        ] },
        { name: 'Staff UI', html_url: 'https://example.test/jobs/3', conclusion: 'skipped', steps: [] },
        { name: 'test-summary', conclusion: null, status: 'in_progress', steps: [] },
    ], [
        { name: 'cypress-junit-api', id: 42, size_in_bytes: 1048576, expired: false, expires_at: '2026-11-14T00:00:00Z' },
        { name: 'cypress-artifacts-old', id: 43, size_in_bytes: 0, expired: true },
        { name: 'build-123', id: 44, size_in_bytes: 1, expired: false },
    ], 'https://example.test/runs/123');
    assert.match(report, /\[API \/ root \/ shard 0\]\(https:\/\/example.test\/jobs\/1\) \| failure \| 4m 5s/);
    assert.match(report, /Cypress &#124; &lt;failed&gt; assertion/);
    assert.match(report, /Staff UI.*skipped \| —/);
    assert.doesNotMatch(report, /test-summary|build-123/);
    assert.match(report, /\[cypress-junit-api\]\(https:\/\/example.test\/runs\/123\/artifacts\/42\) \| 1.0 MiB/);
    assert.match(report, /cypress-artifacts-old \| 0.0 MiB \| Expired/);
    assert.doesNotMatch(report, /artifacts\/43/);
    const timing = report.split('### Slowest completed steps')[1];
    assert.ok(timing.indexOf('Build Docker images') < timing.indexOf('Install'));
});

test('summary command writes a failing aggregate and exits nonzero', t => {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'crm-test-summary-'));
    t.after(() => fs.rmSync(dir, { recursive: true, force: true }));
    const report = path.join(dir, 'summary.md');
    const needs = successful();
    needs['test-api-root'].result = 'failure';
    const result = spawnSync(process.execPath, [path.join(__dirname, '../../scripts/ci/test-run-summary.js'), 'check'], {
        encoding: 'utf8', env: { ...process.env, NEEDS_JSON: JSON.stringify(needs), LOCALE_PR: 'false', GITHUB_STEP_SUMMARY: report },
    });
    assert.equal(result.status, 1, result.stderr);
    assert.match(fs.readFileSync(report, 'utf8'), /Overall: ❌ FAILED/);
});

test('root jobs retain separate spec discovery, all shards, and required summary dependencies', () => {
    const workflow = fs.readFileSync(path.join(__dirname, '../../.github/workflows/build-test-package.yml'), 'utf8');
    assert.doesNotMatch(workflow, /^  test-root:/m);
    for (const [id, area, config, count] of [
        ['test-api-root', 'api', 'docker-api', 2],
        ['test-admin-ui-root', 'ui-admin', 'docker-admin', 3],
        ['test-staff-ui-root', 'ui', 'docker-ui', 4],
    ]) {
        const job = workflow.split(`\n  ${id}:\n`)[1].split(/\n  \S/)[0];
        assert.match(job, /needs: \[configure, build, lint\]/);
        assert.match(job, /fail-fast: false/);
        assert.ok(job.includes(`shard: [${Array.from({ length: count }, (_, index) => `'${index}'`).join(', ')}]`));
        assert.ok(job.includes(`config-file: cypress/configs/${config}.config.ts`));
        assert.ok(job.includes(`spec: cypress/e2e/${area}/**/*.spec.js`));
        assert.ok(job.includes(`shard-total: ${count}`));
        assert.match(job, /shard-index: \$\{\{ matrix.shard }}/);
        assert.match(job, /uses: .\/.github\/workflows\/test-root-suite.yml/);
    }
    const summary = workflow.split('\n  test-summary:\n')[1];
    for (const group of groups) assert.ok(summary.split('    if:')[0].includes(group), group);
    assert.match(summary, /if: always\(\)[\s\S]*NEEDS_JSON: \$\{\{ toJSON\(needs\) }}/);
    assert.equal(workflow.match(/      - '.github\/workflows\/test-root-suite.yml'/g).length, 2);
    const shared = fs.readFileSync(path.join(__dirname, '../../.github/workflows/test-root-suite.yml'), 'utf8');
    assert.match(shared, /- name: Stop Docker\n      if: always\(\)/);
    assert.match(shared, /ROW_GUARD_REQUIRED=1/);
    assert.match(shared, /shard-index: \$\{\{ inputs.shard-index }}/);
});

test('Docker image commands treat the reusable PHP version input as data', t => {
    const workflow = fs.readFileSync(path.join(__dirname, '../../.github/workflows/test-root-suite.yml'), 'utf8');
    const step = workflow.split('    - name: Load Docker image\n')[1].split('\n    - name:')[0];
    assert.match(step, /PHP_VERSION: \$\{\{ inputs.php-version }}/);
    const script = step.split('      run: |\n')[1].split('\n').map(line => line.replace(/^ {8}/, '')).join('\n');
    assert.doesNotMatch(script, /\$\{\{/);
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'crm-docker-input-'));
    t.after(() => fs.rmSync(directory, { recursive: true, force: true }));
    fs.mkdirSync(path.join(directory, 'temp'));
    const version = '8.4-$(touch injected)';
    fs.writeFileSync(path.join(directory, 'temp', `docker-image-${version}.tar.gz`), '');
    const log = path.join(directory, 'docker.log');
    const result = spawnSync('bash', ['-e', '-c', `docker() { printf '%s\\n' "$@" >> "$DOCKER_LOG"; }\n${script}`], {
        encoding: 'utf8', cwd: directory, env: { ...process.env, PHP_VERSION: version, DOCKER_LOG: log },
    });
    assert.equal(result.status, 0, result.stderr);
    assert.equal(fs.existsSync(path.join(directory, 'injected')), false);
    assert.equal(fs.readFileSync(log, 'utf8'), `load\ntag\nchurchcrm/crm:php${version}-test\nchurchcrm/crm:php8-debian\n`);
});
