const fs = require('node:fs');
const { execFileSync } = require('node:child_process');

function cell(value) {
    return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\|/g, '&#124;').replace(/[\r\n]/g, ' ');
}

function seconds(start, end) {
    const duration = (Date.parse(end) - Date.parse(start)) / 1000;
    return Number.isFinite(duration) && duration >= 0 ? duration : null;
}

function duration(value) {
    return value === null ? '—' : `${Math.floor(value / 60)}m ${Math.floor(value % 60)}s`;
}

function render(jobs, artifacts, runUrl) {
    const visible = jobs.filter(job => !['test-summary', 'nightly-summary'].includes(job.name));
    const lines = [
        '## Test overview', '',
        'Open a job for failed tests and logs. JUnit artifacts contain test results; debug artifacts contain screenshots and Docker/PHP logs.', '',
        '| Job | Result | Duration | Failed step |',
        '|-----|--------|---------:|-------------|',
    ];
    for (const job of visible) {
        const failures = (job.steps ?? []).filter(step => ['failure', 'cancelled', 'timed_out'].includes(step.conclusion));
        lines.push(`| [${cell(job.name)}](${job.html_url}) | ${cell(job.conclusion ?? job.status)} | ${duration(seconds(job.started_at, job.completed_at))} | ${failures.map(step => cell(step.name)).join('; ') || '—'} |`);
    }
    const steps = visible.flatMap(job => (job.steps ?? [])
        .filter(step => step.conclusion === 'success' && !/^(Post |Set up job$|Complete job$)/.test(step.name))
        .map(step => ({ job, step, seconds: seconds(step.started_at, step.completed_at) })))
        .filter(step => step.seconds > 0).sort((a, b) => b.seconds - a.seconds).slice(0, 10);
    lines.push('', '### Slowest completed steps', '', '| Job | Step | Duration |', '|-----|------|---------:|');
    for (const item of steps) {
        lines.push(`| [${cell(item.job.name)}](${item.job.html_url}) | ${cell(item.step.name)} | ${duration(item.seconds)} |`);
    }
    lines.push('', '### Cypress artifacts', '', '| Artifact | Size | Availability |', '|----------|-----:|--------------|');
    for (const artifact of artifacts.filter(item => item.name.startsWith('cypress-'))) {
        const label = cell(artifact.name);
        lines.push(`| ${artifact.expired ? label : `[${label}](${runUrl}/artifacts/${artifact.id})`} | ${(artifact.size_in_bytes / 1024 / 1024).toFixed(1)} MiB | ${artifact.expired ? 'Expired' : `Expires ${cell(artifact.expires_at)}`} |`);
    }
    return `${lines.join('\n')}\n`;
}

const required = ['configure', 'lint', 'build', 'test-api-root', 'test-admin-ui-root', 'test-staff-ui-root', 'test-new-system'];

function check(needs, localePR) {
    const bad = required.filter(name => needs[name]?.result !== 'success');
    if (!bad.length) return { ok: true, summary: '**Overall: ✅ PASSED**\n' };
    if (localePR && needs.configure?.result === 'success' && required.slice(1).every(name => needs[name]?.result === 'skipped')) {
        return { ok: true, summary: '**Overall: ✅ LOCALE BRANCH SKIP**\nBuild and Cypress groups were skipped by the locale branch policy.\n' };
    }
    return { ok: false, summary: `**Overall: ❌ FAILED**\nRequired groups did not pass: ${bad.map(name => `${name} (${cell(needs[name]?.result ?? 'missing')})`).join(', ')}.\n` };
}

function main() {
    if (process.argv[2] === 'check') {
        const result = check(JSON.parse(process.env.NEEDS_JSON), process.env.LOCALE_PR === 'true');
        fs.appendFileSync(process.env.GITHUB_STEP_SUMMARY, `\n${result.summary}`);
        process.exitCode = result.ok ? 0 : 1;
        return;
    }
    const repo = `/repos/${process.env.GITHUB_REPOSITORY}/actions/runs/${process.env.GITHUB_RUN_ID}`;
    const read = (endpoint, key) => JSON.parse(execFileSync('gh', ['api', '--paginate', '--slurp', `${repo}${endpoint}`], { encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 })).flatMap(page => page[key]);
    const jobs = read(`/attempts/${process.env.GITHUB_RUN_ATTEMPT}/jobs?per_page=100`, 'jobs');
    const artifacts = read('/artifacts?per_page=100', 'artifacts');
    const runUrl = `${process.env.GITHUB_SERVER_URL}/${process.env.GITHUB_REPOSITORY}/actions/runs/${process.env.GITHUB_RUN_ID}`;
    fs.appendFileSync(process.env.GITHUB_STEP_SUMMARY, render(jobs, artifacts, runUrl));
}

if (require.main === module) main();
module.exports = { render, check };
