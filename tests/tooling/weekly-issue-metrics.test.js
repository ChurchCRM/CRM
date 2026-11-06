const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { TITLE, MARKER, LABELS, startDate, renderReport, publish } = require('../../scripts/maintainer/weekly-issue-metrics');

const now = new Date('2026-11-06T00:00:00Z');
const issue = (number, age, extra = {}) => ({
    number, created_at: new Date(now.getTime() - age * 86400000).toISOString(), labels: [], ...extra,
});
const report = (number, extra = {}) => issue(number, 7, {
    title: TITLE, body: MARKER, labels: LABELS.map((name) => ({ name })), user: { login: 'github-actions[bot]' }, ...extra,
});

test('calculates the UTC window across month and year boundaries', () => {
    assert.equal(startDate(now), '2026-10-07');
    assert.equal(startDate(new Date('2026-01-01T02:00:00+02:00')), '2025-12-02');
});

test('renders both summaries and backlog age boundaries, excluding PRs and reports', () => {
    const issues = [0, 30, 31, 90, 91, 365, 366].map((age, index) => issue(index + 1, age));
    issues.push(issue(8, 900, { pull_request: {} }), report(9));
    const body = renderReport('Recent summary', 'Backlog summary', issues, now);
    assert.ok(body.includes('Recent summary'));
    assert.ok(body.includes('Backlog summary'));
    assert.ok(body.includes('7 open issues'));
    assert.ok(body.includes('| 0–30 days | 2 |'));
    assert.ok(body.includes('| 31–90 days | 2 |'));
    assert.ok(body.includes('| 91–365 days | 2 |'));
    assert.ok(body.includes('| Over 365 days | 1 |'));
    assert.ok(body.indexOf('[#7]') < body.indexOf('[#6]'));
    assert.ok(!body.includes('[#8]'));
    assert.ok(!body.includes('[#9]'));
});

test('handles an empty backlog and limits the oldest issues to ten', () => {
    assert.ok(renderReport('recent', 'backlog', [], now).includes('| None | — |'));
    const body = renderReport('recent', 'backlog', Array.from({ length: 12 }, (_, i) => issue(i + 1, i)), now);
    assert.equal((body.match(/https:\/\/www.github.com/g) || []).length, 10);
    assert.ok(!body.includes('[#1]'));
});

test('paginates and creates the replacement before retiring only marked bot reports', () => {
    const calls = [];
    const api = (...args) => {
        calls.push(args);
        if (args[0] === 'GET') return [
            [report(1), report(2, { user: { login: 'maintainer' } }), report(3, { body: 'Unmarked' })],
            [report(4), report(5, { title: 'Another report' }), report(6, { labels: [] }), issue(7, 100)],
        ];
        if (args[0] === 'POST') return { number: 8 };
        return {};
    };
    assert.equal(publish('recent', 'backlog', api, 'ChurchCRM/CRM', now).number, 8);
    assert.deepEqual(calls[0], ['GET', 'repos/ChurchCRM/CRM/issues?state=open&per_page=100', undefined, true]);
    assert.equal(calls[1][0], 'POST');
    assert.deepEqual(calls[1][2].labels, LABELS);
    assert.deepEqual(calls.slice(2), [1, 4].map((number) => [
        'PATCH', `repos/ChurchCRM/CRM/issues/${number}`, { state: 'closed', state_reason: 'completed' },
    ]));
});

test('a failed create never closes the last report', () => {
    const calls = [];
    const api = (method) => {
        calls.push(method);
        if (method === 'GET') return [[report(1)]];
        throw new Error('Create failed');
    };
    assert.throws(() => publish('recent', 'backlog', api, 'ChurchCRM/CRM', now), /Create failed/);
    assert.deepEqual(calls, ['GET', 'POST']);
});

test('a retry recovers from a failed retirement without touching unrelated issues', () => {
    let open = [report(1), issue(2, 100)];
    let failClose = true;
    const api = (method, endpoint, body) => {
        if (method === 'GET') return [open];
        if (method === 'POST') {
            const created = report(open.length + 1, { ...body, labels: body.labels.map((name) => ({ name })) });
            open.push(created);
            return created;
        }
        if (failClose) {
            failClose = false;
            throw new Error('Retirement failed');
        }
        open = open.filter(({ number }) => !endpoint.endsWith(`/${number}`));
        return {};
    };
    assert.throws(() => publish('recent', 'backlog', api, 'ChurchCRM/CRM', now), /Retirement failed/);
    const created = publish('recent', 'backlog', api, 'ChurchCRM/CRM', now);
    assert.deepEqual(open.map(({ number }) => number), [2, created.number]);
});

test('invalid input and oversized output fail before creating or closing any issue', () => {
    for (const [recent, backlog, pages, error] of [
        ['', 'backlog', [[]], /nonempty/],
        ['recent', 'backlog', {}, /Incomplete/],
        ['recent', 'backlog', [[issue(1, 0, { created_at: 'invalid' })]], /Invalid creation/],
        ['x'.repeat(65000), 'backlog', [[]], /body limit/],
    ]) {
        const calls = [];
        const api = (method) => { calls.push(method); return pages; };
        assert.throws(() => publish(recent, backlog, api, 'ChurchCRM/CRM', now), error);
        assert.deepEqual(calls, ['GET']);
    }
    assert.throws(() => publish('recent', 'backlog', () => assert.fail('No API calls expected'), 'fork/CRM'), /only be published/);
});

test('workflow serializes publication and preserves independent, bounded metrics outputs', () => {
    const yaml = require('yaml');
    const workflow = yaml.parse(fs.readFileSync(path.join(__dirname, '../../.github/workflows/weekly-issue-metrics.yml'), 'utf8'));
    assert.equal(workflow.on.schedule[0].cron, '0 0 * * 1');
    assert.ok(Object.hasOwn(workflow.on, 'workflow_dispatch'));
    assert.equal(workflow.concurrency['cancel-in-progress'], false);
    assert.ok(!workflow.concurrency.group.includes('github.ref'));
    assert.deepEqual(workflow.permissions, { contents: 'read' });
    const steps = workflow.jobs.report.steps;
    const metrics = steps.filter((step) => step.uses?.startsWith('github-community-projects/issue-metrics@'));
    assert.equal(metrics.length, 2);
    assert.ok(metrics[0].env.SEARCH_QUERY.includes('created:>=${{ env.START_DATE }}'));
    assert.ok(!metrics[0].env.SEARCH_QUERY.includes('is:open'));
    assert.ok(metrics[1].env.SEARCH_QUERY.includes('is:open'));
    assert.ok(!metrics[1].env.SEARCH_QUERY.includes('created:'));
    assert.notEqual(metrics[0].env.OUTPUT_FILE, metrics[1].env.OUTPUT_FILE);
    for (const { env } of metrics) {
        assert.ok(env.SEARCH_QUERY.includes('-label:automated-report'));
        assert.equal(env.HIDE_ITEMS_LIST, 'true');
    }
    assert.match(steps.at(-1).run, /weekly-issue-metrics.js publish$/);
});
