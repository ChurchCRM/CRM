#!/usr/bin/env node

const { execFileSync } = require('node:child_process');
const fs = require('node:fs');

const TITLE = 'Weekly Issue Metrics & Age Report';
const MARKER = '<!-- churchcrm-weekly-issue-metrics -->';
const LABELS = ['metrics', 'automated-report'];
const DAY = 24 * 60 * 60 * 1000;

function startDate(now = new Date()) {
    return new Date(now.getTime() - 30 * DAY).toISOString().slice(0, 10);
}

function gh(method, endpoint, body, paginate = false) {
    const args = ['api', '-X', method, endpoint];
    if (paginate) args.push('--paginate', '--slurp');
    if (body) args.push('--input', '-');
    return JSON.parse(execFileSync('gh', args, {
        encoding: 'utf8', input: body ? JSON.stringify(body) : undefined, maxBuffer: 20 * 1024 * 1024,
    }));
}

function renderReport(recent, backlog, issues, now = new Date()) {
    if (!recent.trim() || !backlog.trim()) throw new Error('Both metrics reports must be nonempty');
    const open = issues.filter((issue) => !issue.pull_request && !issue.labels.some((label) => label.name === 'automated-report'));
    const ages = open.map((issue) => {
        const age = Math.floor((now.getTime() - Date.parse(issue.created_at)) / DAY);
        if (!Number.isFinite(age) || age < 0) throw new Error(`Invalid creation date on issue ${issue.number}`);
        return { ...issue, age };
    }).sort((a, b) => b.age - a.age || a.number - b.number);
    const buckets = [
        ['0–30 days', 0, 30], ['31–90 days', 31, 90], ['91–365 days', 91, 365], ['Over 365 days', 366, Infinity],
    ];
    const ageTable = buckets.map(([label, min, max]) => `| ${label} | ${ages.filter(({ age }) => age >= min && age <= max).length} |`).join('\n');
    const oldest = ages.slice(0, 10).map(({ number, age }) => `| [#${number}](https://www.github.com/ChurchCRM/CRM/issues/${number}) | ${age} |`).join('\n');
    return `${MARKER}
# ${TITLE}

Generated ${now.toISOString()}. Automated reports are excluded from both sections.

## Past 30 days: responsiveness

Issues created since ${startDate(now)} UTC, including issues already closed. Time to close covers only closed issues; unanswered and still-open issues have no completed response or closure duration.

${recent.trim()}

## Full open backlog

${backlog.trim()}

### Backlog age at report time

${ages.length} open issues. Ages are whole days since creation, fetched from all pages of the open-issues API.

| Age | Issues |
| --- | ---: |
${ageTable}

### Ten oldest open issues

| Issue | Age (days) |
| --- | ---: |
${oldest || '| None | — |'}
`;
}

function publish(recent, backlog, api = gh, repo = process.env.GITHUB_REPOSITORY, now = new Date()) {
    if (repo !== 'ChurchCRM/CRM') throw new Error('Reports may only be published in ChurchCRM/CRM');
    const pages = api('GET', `repos/${repo}/issues?state=open&per_page=100`, undefined, true);
    if (!Array.isArray(pages) || !pages.every(Array.isArray)) throw new Error('Incomplete open issue list');
    const issues = pages.flat();
    const body = renderReport(recent, backlog, issues, now);
    if ([...body].length > 65000) throw new Error('Report exceeds the issue body limit');
    const previous = issues.filter((issue) => !issue.pull_request && issue.title === TITLE &&
        issue.body?.includes(MARKER) && issue.user?.login === 'github-actions[bot]' &&
        LABELS.every((name) => issue.labels.some((label) => label.name === name)));
    const created = api('POST', `repos/${repo}/issues`, { title: TITLE, body, labels: LABELS });
    if (!Number.isSafeInteger(created?.number)) throw new Error('Report creation did not return an issue number');
    for (const issue of previous) {
        api('PATCH', `repos/${repo}/issues/${issue.number}`, { state: 'closed', state_reason: 'completed' });
    }
    return created;
}

if (require.main === module) {
    const command = process.argv[2];
    if (command === 'dates') {
        fs.appendFileSync(process.env.GITHUB_ENV, `START_DATE=${startDate()}\n`);
    } else if (command === 'publish') {
        const created = publish(fs.readFileSync('recent-issue-metrics.md', 'utf8'), fs.readFileSync('backlog-issue-metrics.md', 'utf8'));
        console.log(`Published report #${created.number}`);
    } else {
        throw new Error('Expected dates or publish');
    }
}

module.exports = { TITLE, MARKER, LABELS, startDate, renderReport, publish };
