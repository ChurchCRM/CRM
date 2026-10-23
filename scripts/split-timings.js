#!/usr/bin/env node

/**
 * cypress/split-timings.json holds one duration per sharded spec (api, ui, ui-admin). scripts/shard-specs.js reads it
 * to give every shard about the same run time.
 *
 *   node scripts/split-timings.js check
 *       Fails on a malformed file, a duplicate, an unsorted list, or an entry whose spec no
 *       longer exists. A spec missing from the file is fine: it gets the average duration.
 *
 *   node scripts/split-timings.js update [--area api|ui|ui-admin] LOG...
 *       Rewrites the file from the "Run Finished" tables of Cypress job logs
 *       (gh run view --job <id> --log > job.log). Several logs of one spec are averaged.
 *       --area names the spec folder the following logs ran; it keeps a name that exists
 *       in two folders from being skipped as ambiguous.
 */

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const timingsFile = path.join('cypress', 'split-timings.json');
const AREAS = ['api', 'ui', 'ui-admin'];
// Cypress spends about 2 s per spec file outside the spec: start-up and the row-count guard.
const PER_SPEC_OVERHEAD_MS = 2000;
const ANSI = /\x1b\[[0-9;]*m/g;

function readIfPresent(read) {
    try {
        return read();
    } catch (error) {
        if (error.code === 'ENOENT') {
            return null;
        }
        throw error;
    }
}

function listSpecs(repo, area) {
    const dir = path.join(repo, 'cypress', 'e2e', area);
    const walk = (current) =>
        (readIfPresent(() => fs.readdirSync(current, { withFileTypes: true })) ?? []).flatMap((entry) => {
            const full = path.join(current, entry.name);
            if (entry.isDirectory()) {
                return walk(full);
            }
            return entry.name.endsWith('.spec.js') ? [path.relative(repo, full).split(path.sep).join('/')] : [];
        });
    return walk(dir);
}

function check(repo = root) {
    const file = path.join(repo, timingsFile);
    const problems = [];
    let parsed;
    try {
        parsed = JSON.parse(fs.readFileSync(file, 'utf8'));
    } catch (error) {
        return [`${timingsFile}: ${error.message}`];
    }
    if (!Array.isArray(parsed.durations)) {
        return [`${timingsFile}: "durations" must be an array`];
    }

    const seen = new Set();
    parsed.durations.forEach(({ spec, duration }, index) => {
        if (typeof spec !== 'string' || !Number.isInteger(duration) || duration <= 0) {
            problems.push(`${timingsFile}: entry ${index} needs a spec string and a positive integer duration (ms)`);
            return;
        }
        if (seen.has(spec)) {
            problems.push(`${timingsFile}: ${spec} is listed twice`);
        }
        seen.add(spec);
        if (!fs.existsSync(path.join(repo, spec))) {
            problems.push(`${timingsFile}: ${spec} does not exist; remove the entry`);
        }
        if (index > 0 && parsed.durations[index - 1].spec > spec) {
            problems.push(`${timingsFile}: ${spec} is out of order; keep the list sorted by spec`);
        }
    });
    return problems;
}

// Rows of the Cypress summary table. A long path wraps onto continuation lines.
function parseRunFinished(log) {
    const text = log
        .replace(ANSI, '')
        .replace(/^[^\t\n]*\t[^\t\n]*\t(?=\S+Z )/gm, '')
        .replace(/^\S+Z /gm, '');
    const start = text.indexOf('(Run Finished)');
    if (start === -1) {
        return [];
    }
    const rows = [];
    let current = null;
    text
        .slice(start)
        .split('\n')
        .filter((line) => line.includes('│'))
        .forEach((line) => {
            const body = line.trim().replace(/^│/, '').replace(/│$/, '').trimEnd();
            const match = /^\s*[✔✖!-]\s{1,3}(\S.*?)\s{2,}(?:(\d\d):(\d\d)|(\d+)ms)\s+(?:\d+|-)\s/.exec(`${body} `);
            if (match) {
                const seconds = match[4] === undefined ? Number(match[2]) * 60 + Number(match[3]) : Number(match[4]) / 1000;
                current = { name: match[1], seconds };
                rows.push(current);
            } else if (current && body.trim()) {
                current.name += body.trim();
            }
        });
    return rows;
}

// A log is a string, or { text, area } when the folder it ran is known.
function update(logs, repo = root) {
    const specs = AREAS.flatMap((area) => listSpecs(repo, area));
    const samples = {};
    logs.forEach((log) =>
        parseRunFinished(log.text ?? log).forEach(({ name, seconds }) => {
            const candidates = log.area ? specs.filter((spec) => spec.startsWith(`cypress/e2e/${log.area}/`)) : specs;
            const matches = candidates.filter((spec) => spec.endsWith(`/${name}`));
            if (matches.length === 1) {
                (samples[matches[0]] ||= []).push(seconds);
            }
        }),
    );

    const file = path.join(repo, timingsFile);
    const existing = readIfPresent(() => fs.readFileSync(file, 'utf8'));
    const previous = existing === null ? [] : JSON.parse(existing).durations;
    const merged = new Map(previous.filter(({ spec }) => specs.includes(spec)).map(({ spec, duration }) => [spec, duration]));
    Object.entries(samples).forEach(([spec, seconds]) => {
        const average = seconds.reduce((sum, value) => sum + value, 0) / seconds.length;
        merged.set(spec, Math.round(average * 1000) + PER_SPEC_OVERHEAD_MS);
    });

    const durations = [...merged].sort(([a], [b]) => (a < b ? -1 : 1)).map(([spec, duration]) => ({ spec, duration }));
    fs.writeFileSync(file, `${JSON.stringify({ durations }, null, 2)}\n`);
    return { updated: Object.keys(samples).length, total: durations.length };
}

if (require.main === module) {
    const [command = 'check', ...rest] = process.argv.slice(2);
    if (command === 'check') {
        const problems = check();
        problems.forEach((problem) => console.error(problem));
        process.exit(problems.length ? 1 : 0);
    } else if (command === 'update' && rest.length) {
        let area = null;
        const logs = [];
        for (let i = 0; i < rest.length; i += 1) {
            if (rest[i] === '--area') {
                area = rest[(i += 1)];
            } else {
                logs.push({ text: fs.readFileSync(rest[i], 'utf8'), area });
            }
        }
        const result = update(logs);
        console.log(`Updated ${result.updated} specs; ${result.total} in ${timingsFile}.`);
    } else {
        console.error('usage: split-timings.js check | update [--area NAME] LOG...');
        process.exit(2);
    }
}

module.exports = { check, parseRunFinished, update };
