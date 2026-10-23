const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { test } = require('node:test');
const { check, parseRunFinished, update } = require('./split-timings');

function repoWith(specs, durations) {
    const repo = fs.mkdtempSync(path.join(os.tmpdir(), 'split-timings-'));
    specs.forEach((spec) => {
        fs.mkdirSync(path.dirname(path.join(repo, spec)), { recursive: true });
        fs.writeFileSync(path.join(repo, spec), '');
    });
    fs.mkdirSync(path.join(repo, 'cypress'), { recursive: true });
    if (durations) {
        fs.writeFileSync(path.join(repo, 'cypress', 'split-timings.json'), JSON.stringify({ durations }));
    }
    return repo;
}

const LOG = [
    '2026-10-22T09:31:04.7Z   (\u001b[4m\u001b[1mRun Finished\u001b[22m\u001b[24m)',
    '2026-10-22T09:31:04.8Z   │ ✔  avatar-click.spec.js                     00:13       13       13        -        -        - │',
    '2026-10-22T09:31:04.8Z   │ ✔  communication/communication.admin-m      01:04        2        2        -        -        - │',
    '2026-10-22T09:31:04.8Z   │    enu.spec.js                                                                                 │',
    '2026-10-22T09:31:04.8Z   │ ✔  public/public.calendar.spec.js           963ms        5        5        -        -        - │',
].join('\n');

test('parseRunFinished joins a wrapped path and reads minutes, seconds and milliseconds', () => {
    assert.deepEqual(parseRunFinished(LOG), [
        { name: 'avatar-click.spec.js', seconds: 13 },
        { name: 'communication/communication.admin-menu.spec.js', seconds: 64 },
        { name: 'public/public.calendar.spec.js', seconds: 0.963 },
    ]);
});

test('parseRunFinished reads the job and step prefix that gh run view --log adds', () => {
    const log = [
        'test-root\tRun Cypress\t2026-10-22T09:31:04.7Z   (Run Finished)',
        'test-root\tRun Cypress\t2026-10-22T09:31:04.8Z   │ ✔  avatar-click.spec.js                     00:13       13       13        -        -        - │',
    ].join('\n');
    assert.deepEqual(parseRunFinished(log), [{ name: 'avatar-click.spec.js', seconds: 13 }]);
});

test('check passes a sorted file of existing specs', () => {
    const repo = repoWith(['cypress/e2e/ui/a.spec.js', 'cypress/e2e/ui/b.spec.js'], [
        { spec: 'cypress/e2e/ui/a.spec.js', duration: 1000 },
        { spec: 'cypress/e2e/ui/b.spec.js', duration: 2000 },
    ]);
    assert.deepEqual(check(repo), []);
});

test('check reports a deleted spec, a duplicate, a bad duration and a wrong order', () => {
    const repo = repoWith(['cypress/e2e/ui/a.spec.js', 'cypress/e2e/ui/b.spec.js'], [
        { spec: 'cypress/e2e/ui/b.spec.js', duration: 2000 },
        { spec: 'cypress/e2e/ui/a.spec.js', duration: 1000 },
        { spec: 'cypress/e2e/ui/a.spec.js', duration: 1000 },
        { spec: 'cypress/e2e/ui/gone.spec.js', duration: 1000 },
        { spec: 'cypress/e2e/ui/c.spec.js', duration: 0 },
    ]);
    const problems = check(repo).join('\n');
    assert.match(problems, /listed twice/);
    assert.match(problems, /does not exist/);
    assert.match(problems, /positive integer duration/);
    assert.match(problems, /out of order/);
});

test('check reports a missing file', () => {
    assert.equal(check(repoWith([], null)).length, 1);
});

test('update averages samples, keeps other entries and drops deleted specs', () => {
    const repo = repoWith(['cypress/e2e/ui/a.spec.js', 'cypress/e2e/ui-admin/b.spec.js', 'cypress/e2e/ui/c.spec.js'], [
        { spec: 'cypress/e2e/ui/c.spec.js', duration: 5000 },
        { spec: 'cypress/e2e/ui/gone.spec.js', duration: 9000 },
    ]);
    const row = (name, time) => `│ ✔  ${name}   ${time}   1   1   -   -   - │`;
    const logs = [
        `(Run Finished)\n${row('a.spec.js', '00:10')}\n${row('b.spec.js', '00:30')}`,
        `(Run Finished)\n${row('a.spec.js', '00:20')}`,
    ];
    assert.deepEqual(update(logs, repo), { updated: 2, total: 3 });
    const written = JSON.parse(fs.readFileSync(path.join(repo, 'cypress', 'split-timings.json'), 'utf8')).durations;
    assert.deepEqual(written, [
        { spec: 'cypress/e2e/ui-admin/b.spec.js', duration: 32000 },
        { spec: 'cypress/e2e/ui/a.spec.js', duration: 17000 },
        { spec: 'cypress/e2e/ui/c.spec.js', duration: 5000 },
    ]);
});

test('update reads a name that exists in two folders when the folder is given', () => {
    const repo = repoWith(['cypress/e2e/api/people/a.spec.js', 'cypress/e2e/ui/people/a.spec.js'], null);
    const log = '(Run Finished)\n│ ✔  people/a.spec.js   00:10   1   1   -   -   - │';
    assert.deepEqual(update([{ text: log, area: 'api' }], repo), { updated: 1, total: 1 });
    assert.deepEqual(update([log], repo), { updated: 0, total: 1 });
    const written = JSON.parse(fs.readFileSync(path.join(repo, 'cypress', 'split-timings.json'), 'utf8')).durations;
    assert.deepEqual(written, [{ spec: 'cypress/e2e/api/people/a.spec.js', duration: 12000 }]);
});
