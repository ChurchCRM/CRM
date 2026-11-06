#!/usr/bin/env node

const { execFileSync } = require('node:child_process');
const fs = require('node:fs');

const TITLE = 'Weekly Issue Metrics & Age Report';
const DAY = 24 * 60 * 60 * 1000;

function startDate(now = new Date()) {
    return new Date(now.getTime() - 30 * DAY).toISOString().slice(0, 10);
}

function gh(endpoint) {
    return JSON.parse(execFileSync('gh', ['api', '--method', 'GET', '--paginate', '--slurp', endpoint], {
        encoding: 'utf8', maxBuffer: 20 * 1024 * 1024,
    }));
}

function listAll(endpoint, api = gh) {
    const pages = api(endpoint);
    if (!Array.isArray(pages) || !pages.every(Array.isArray)) throw new Error(`Incomplete list: ${endpoint}`);
    return pages.flat();
}

function queries(milestone = '', api = gh, now = new Date()) {
    if (/[\r\n]/.test(milestone)) throw new Error('Milestone title must be a single line');
    let number = '';
    if (milestone) {
        const found = listAll('repos/ChurchCRM/CRM/milestones?state=all&per_page=100', api).find(({ title }) => title === milestone);
        if (!Number.isSafeInteger(found?.number)) throw new Error(`Milestone not found: ${milestone}`);
        number = String(found.number);
    }
    const scope = `repo:ChurchCRM/CRM is:issue -label:automated-report${milestone ? ` milestone:${JSON.stringify(milestone)}` : ''}`;
    return {
        recent_query: `${scope} created:>=${startDate(now)}`,
        backlog_query: `${scope} is:open`,
        milestone_number: number,
    };
}

function renderReport(recent, backlog, issues, now = new Date(), milestone = '') {
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
    return `# ${TITLE}

Generated ${now.toISOString()}. Automated reports are excluded from both sections.

Scope: ${milestone ? `milestone ${JSON.stringify(milestone)}` : 'all milestones, including issues without a milestone'}.

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

function buildReport(recent, backlog, api = gh, repo = process.env.GITHUB_REPOSITORY, now = new Date(), milestoneNumber = '', milestone = '') {
    if (repo !== 'ChurchCRM/CRM') throw new Error('Reports may only be generated for ChurchCRM/CRM');
    if ((milestone && !/^[1-9]\d*$/.test(milestoneNumber)) || (!milestone && milestoneNumber)) throw new Error('Invalid milestone scope');
    const issues = listAll(`repos/${repo}/issues?state=open&per_page=100${milestoneNumber ? `&milestone=${milestoneNumber}` : ''}`, api);
    return renderReport(recent, backlog, issues, now, milestone);
}

function writeReport(body, summaryPath = process.env.GITHUB_STEP_SUMMARY, outputPath = 'weekly-issue-metrics.md') {
    if (!summaryPath) throw new Error('GITHUB_STEP_SUMMARY is required');
    fs.writeFileSync(outputPath, body);
    fs.appendFileSync(summaryPath, body);
}

if (require.main === module) {
    const command = process.argv[2];
    if (command === 'dates') {
        const output = queries(process.env.METRICS_MILESTONE || '');
        fs.appendFileSync(process.env.GITHUB_OUTPUT, Object.entries(output).map(([key, value]) => `${key}=${value}\n`).join(''));
    } else if (command === 'report') {
        const body = buildReport(fs.readFileSync('recent-issue-metrics.md', 'utf8'), fs.readFileSync('backlog-issue-metrics.md', 'utf8'),
            gh, process.env.GITHUB_REPOSITORY, new Date(), process.env.METRICS_MILESTONE_NUMBER || '', process.env.METRICS_MILESTONE || '');
        writeReport(body);
        console.log('Attached weekly-issue-metrics.md to the run summary');
    } else {
        throw new Error('Expected dates or report');
    }
}

module.exports = { startDate, queries, renderReport, buildReport, writeReport };
