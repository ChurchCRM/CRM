const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

const setup = path.join(__dirname, 'setup-dev.sh');

function simulate(args, failTool = '') {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'churchcrm-setup-'));
    const log = path.join(dir, 'calls');
    const mock = `#!/bin/bash
name=\"\${0##*/}\"
echo \"$name $* CYPRESS_INSTALL_BINARY=\${CYPRESS_INSTALL_BINARY:-}\" >> \"$SETUP_TEST_LOG\"
[ \"$name\" != \"$SETUP_TEST_FAIL_TOOL\" ] || exit 1
if [ \"$SETUP_TEST_FAIL_TOOL\" = verify ] && [ \"$name\" = npx ] && [ \"$*\" = '--no-install cypress verify' ]; then exit 1; fi
if [ \"$name\" = npx ] && [ \"$*\" = '--no-install cypress install' ]; then exit 1; fi
exit 0
`;
    try {
        for (const tool of ['node', 'npm', 'php', 'composer', 'git', 'docker', 'npx', 'curl']) {
            fs.writeFileSync(path.join(dir, tool), mock, { mode: 0o755 });
        }
        const result = spawnSync('bash', [setup, ...args], {
            encoding: 'utf8',
            env: { ...process.env, PATH: `${dir}:/usr/bin:/bin`, SETUP_TEST_LOG: log, SETUP_TEST_FAIL_TOOL: failTool },
        });
        return { ...result, calls: fs.existsSync(log) ? fs.readFileSync(log, 'utf8') : '' };
    } finally {
        fs.rmSync(dir, { recursive: true, force: true });
    }
}

test('invalid setup options fail before invoking installers', () => {
    const result = simulate(['--bad-option']);
    assert.equal(result.status, 2);
    assert.equal(result.calls, '');
});

test('unsupported PHP stops setup before dependency installation', () => {
    const result = simulate([], 'php');
    assert.equal(result.status, 1);
    assert.match(result.stderr, /PHP 8.4/);
    assert.doesNotMatch(result.calls, /npm ci/);
});

test('native setup defers Cypress and stops when dependency install fails', () => {
    const result = simulate([], 'npm');
    assert.equal(result.status, 1);
    assert.match(result.calls, /npm ci CYPRESS_INSTALL_BINARY=0/);
    assert.doesNotMatch(result.calls, /run build/);
});

test('integration retries a failed Cypress download once, verifies it, then starts services', () => {
    const result = simulate(['--integration']);
    assert.equal(result.status, 0, result.stderr);
    const calls = result.calls;
    assert.equal((calls.match(/npx --no-install cypress install/g) || []).length, 2);
    assert.ok(calls.indexOf('cypress install --force') < calls.indexOf('cypress verify'));
    assert.ok(calls.indexOf('cypress verify') < calls.indexOf('npm run docker:test:start'));
    assert.match(calls, /check-environment.js integration --services/);
});

test('browser verification failure stops before services start', () => {
    const result = simulate(['--integration'], 'verify');
    assert.equal(result.status, 1);
    assert.doesNotMatch(result.calls, /npm run docker:test:start/);
});

test('native setup installs locked PHP test tooling before readiness and lint', () => {
    const result = simulate([]);
    assert.equal(result.status, 0, result.stderr);
    assert.ok(result.calls.indexOf('npm run build') < result.calls.indexOf('npm run test:php:install'));
    assert.ok(result.calls.indexOf('npm run test:php:install') < result.calls.indexOf('check-environment.js php'));
    assert.ok(result.calls.indexOf('check-environment.js php') < result.calls.indexOf('npm run lint'));
});


test('container setup uses the existing image and runs shared setup inside it', () => {
    const result = simulate(['--docker'], 'php');
    assert.equal(result.status, 0, result.stderr);
    assert.match(result.calls, /docker compose -f docker\/docker-compose.dev.yaml up -d --build/);
    assert.match(result.calls, /bash scripts\/setup-dev.sh/);
    assert.doesNotMatch(result.calls, /php -r/);
});
