const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { startDate, queries, renderReport, buildReport, writeReport } = require('../../scripts/maintainer/weekly-issue-metrics');

const now = new Date('2026-11-06T00:00:00Z');
const issue = (number, age, extra = {}) => ({
    number, created_at: new Date(now.getTime() - age * 86400000).toISOString(), labels: [], ...extra,
});

test('calculates the UTC window across month and year boundaries', () => {
    assert.equal(startDate(now), '2026-10-07');
    assert.equal(startDate(new Date('2026-01-01T02:00:00+02:00')), '2025-12-02');
});

test('default queries cover all milestones and allow closed issues in the recent window', () => {
    const output = queries('', () => assert.fail('No milestone lookup needed'), now);
    assert.equal(output.recent_query, 'repo:ChurchCRM/CRM is:issue -label:automated-report created:>=2026-10-07');
    assert.equal(output.backlog_query, 'repo:ChurchCRM/CRM is:issue -label:automated-report is:open');
    assert.equal(output.milestone_number, '');
});

test('resolves milestone titles across all pages, including closed milestones', () => {
    const output = queries('Release 7.8.3', (endpoint) => {
        assert.equal(endpoint, 'repos/ChurchCRM/CRM/milestones?state=all&per_page=100');
        return [[{ number: 1, title: '7.8.2' }], [{ number: 245, title: 'Release 7.8.3', state: 'closed' }]];
    }, now);
    assert.ok(output.recent_query.includes('milestone:"Release 7.8.3"'));
    assert.ok(output.backlog_query.includes('milestone:"Release 7.8.3"'));
    assert.equal(output.milestone_number, '245');
    assert.throws(() => queries('missing', () => [[]], now), /Milestone not found/);
    assert.throws(() => queries('bad\nquery'), /single line/);
});

test('renders summaries and age boundaries, excluding PRs and reports', () => {
    const issues = [0, 30, 31, 90, 91, 365, 366].map((age, index) => issue(index + 1, age));
    issues.push(issue(8, 900, { pull_request: {} }), issue(9, 7, { labels: [{ name: 'automated-report' }] }));
    const body = renderReport('Recent summary', 'Backlog summary', issues, now);
    for (const text of ['Recent summary', 'Backlog summary', '7 open issues',
        '| 0–30 days | 2 |', '| 31–90 days | 2 |', '| 91–365 days | 2 |', '| Over 365 days | 1 |',
        'all milestones, including issues without a milestone']) assert.ok(body.includes(text), text);
    assert.ok(body.indexOf('[#7]') < body.indexOf('[#6]'));
    assert.ok(!body.includes('[#8]'));
    assert.ok(!body.includes('[#9]'));
});

test('handles an empty backlog and limits the oldest issues to ten', () => {
    assert.ok(renderReport('recent', 'backlog', [], now).includes('| None | — |'));
    const body = renderReport('recent', 'backlog', Array.from({ length: 12 }, (_, i) => issue(i + 1, i)), now);
    assert.equal(body.split('https://www.github.com/').length - 1, 10);
    assert.ok(!body.includes('[#1]'));
});

test('reads every open-issue page without mutating issues', () => {
    const calls = [];
    const body = buildReport('recent', 'backlog', (endpoint) => {
        calls.push(endpoint);
        return [[issue(1, 10)], [issue(2, 100)]];
    }, 'ChurchCRM/CRM', now);
    assert.deepEqual(calls, ['repos/ChurchCRM/CRM/issues?state=open&per_page=100']);
    assert.ok(body.includes('2 open issues'));
});

test('applies the milestone to the age table and identifies the scope', () => {
    const body = buildReport('recent', 'backlog', (endpoint) => {
        assert.equal(endpoint, 'repos/ChurchCRM/CRM/issues?state=open&per_page=100&milestone=245');
        return [[issue(1, 100)]];
    }, 'ChurchCRM/CRM', now, '245', '7.8.3');
    assert.ok(body.includes('Scope: milestone "7.8.3"'));
    assert.ok(body.includes('1 open issues'));
    assert.throws(() => buildReport('recent', 'backlog', () => assert.fail(), 'ChurchCRM/CRM', now, '', '7.8.3'), /Invalid milestone scope/);
});

test('writes matching Markdown artifact and run summary content', () => {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'crm-metrics-'));
    try {
        const summary = path.join(dir, 'summary.md');
        const output = path.join(dir, 'weekly-issue-metrics.md');
        fs.writeFileSync(summary, 'Existing summary\n');
        const body = renderReport('recent', 'backlog', [], now);
        writeReport(body, summary, output);
        assert.equal(fs.readFileSync(output, 'utf8'), body);
        assert.equal(fs.readFileSync(summary, 'utf8'), `Existing summary\n${body}`);
        assert.throws(() => writeReport(body, ''), /GITHUB_STEP_SUMMARY/);
    } finally {
        fs.rmSync(dir, { recursive: true, force: true });
    }
});

test('rejects missing summaries, incomplete pages and invalid creation dates', () => {
    assert.throws(() => buildReport('', 'backlog', () => [[]], 'ChurchCRM/CRM', now), /nonempty/);
    assert.throws(() => buildReport('recent', 'backlog', () => ({}), 'ChurchCRM/CRM', now), /Incomplete/);
    assert.throws(() => renderReport('recent', 'backlog', [issue(1, 0, { created_at: 'invalid' })], now), /Invalid creation/);
    assert.throws(() => buildReport('recent', 'backlog', () => assert.fail(), 'fork/CRM'), /only be generated/);
});

test('workflow attaches both outputs with read-only access and an optional milestone', () => {
    const yaml = require('yaml');
    const workflow = yaml.parse(fs.readFileSync(path.join(__dirname, '../../.github/workflows/weekly-issue-metrics.yml'), 'utf8'));
    assert.equal(workflow.on.schedule[0].cron, '0 0 * * 1');
    assert.equal(workflow.on.workflow_dispatch.inputs.milestone.required, false);
    assert.equal(workflow.jobs.report.permissions.issues, 'read');
    assert.deepEqual(workflow.permissions, { contents: 'read' });
    const steps = workflow.jobs.report.steps;
    const metrics = steps.filter((step) => step.uses?.startsWith('github-community-projects/issue-metrics@'));
    assert.equal(metrics.length, 2);
    assert.equal(metrics[0].env.SEARCH_QUERY, '${{ steps.queries.outputs.recent_query }}');
    assert.equal(metrics[1].env.SEARCH_QUERY, '${{ steps.queries.outputs.backlog_query }}');
    assert.notEqual(metrics[0].env.OUTPUT_FILE, metrics[1].env.OUTPUT_FILE);
    assert.ok(metrics.every(({ env }) => env.HIDE_ITEMS_LIST === 'true'));
    assert.match(steps.at(-2).run, /weekly-issue-metrics.js report$/);
    assert.equal(steps.at(-2).env.METRICS_MILESTONE_NUMBER, '${{ steps.queries.outputs.milestone_number }}');
    assert.equal(steps.at(-1).uses, './.github/actions/upload-artifact');
    assert.equal(steps.at(-1).with.path, 'weekly-issue-metrics.md');
    assert.equal(steps.at(-1).with['if-no-files-found'], 'error');
});
