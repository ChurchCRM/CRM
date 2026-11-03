const { test } = require('node:test');
const assert = require('node:assert/strict');
const { options, checkEnvironment } = require('./check-environment');

const ready = (bin, args) => ({
    status: 0,
    stdout: args.includes('--version') ? 'v24.0.0' : 'database\nwebserver\nmailserver\n',
});

test('rejects ambiguous modes and service checks without integration', () => {
    for (const args of [['--unknown'], ['static', 'php'], ['php', '--services']]) {
        assert.throws(() => options(args));
    }
    assert.deepEqual(options([]), { mode: 'static', services: false });
});

test('reports unavailable tools rather than throwing or installing them', () => {
    const calls = [];
    const results = checkEnvironment(options(['php']), (bin, args) => {
        calls.push([bin, args]);
        return { status: null, stdout: '', error: new Error('ENOENT') };
    }, () => false);
    assert.ok(results.every((result) => !result.ok && result.remedy));
    assert.ok(calls.every(([, args]) => !args.includes('install')));
});

test('an older Node runtime fails even when the command succeeds', () => {
    const results = checkEnvironment(options([]), (bin, args) => {
        if (bin === process.execPath && args[0] === '--version') return { status: 0, stdout: 'v22.0.0' };
        return ready(bin, args);
    }, () => true);
    assert.equal(results.find((item) => item.name === 'Node runtime').ok, false);
});

test('static mode does not require a database, vendor tree, browser, or Docker', () => {
    const calls = [];
    const results = checkEnvironment(options([]), (bin, args) => {
        calls.push([bin, args]);
        return ready(bin, args);
    }, () => true);
    assert.ok(results.every((result) => result.ok));
    assert.ok(calls.every(([bin, args]) => bin !== 'docker' && !args.includes('verify') && !args.includes('check-platform-reqs')));
});

test('PHP mode checks the ORM/installer extensions as well as Composer requirements', () => {
    const calls = [];
    const results = checkEnvironment(options(['php']), (bin, args) => {
        calls.push([bin, args]);
        return ready(bin, args);
    }, () => true);
    assert.ok(results.every((result) => result.ok));
    const code = calls.find(([bin, args]) => bin === 'php' && args[1].includes('extension_loaded'))[1][1];
    for (const extension of ['gettext', 'pdo_mysql', 'simplexml', 'phar', 'zip']) assert.ok(code.includes(`"${extension}"`));
});

test('integration fails on a missing service, unhealthy DB, or failed HTTP request', () => {
    for (const failure of ['services', 'database', 'http']) {
        const results = checkEnvironment(options(['integration', '--services']), (bin, args) => {
            if (failure === 'services' && args.includes('ps')) return { status: 0, stdout: 'database\nwebserver\n' };
            if (failure === 'database' && args.includes('healthcheck.sh')) return { status: 1, stdout: '' };
            if (failure === 'http' && bin === 'curl') return { status: 1, stdout: '' };
            return ready(bin, args);
        }, () => true);
        assert.equal(results.filter((result) => !result.ok).length, 1, failure);
    }
});

test('PHP readiness identifies missing test tooling', () => {
    const results = checkEnvironment(options(['php']), ready, (file) => file !== 'tests/php/vendor/bin/tester');
    assert.deepEqual(results.filter((result) => !result.ok).map((result) => result.name), ['PHP test runner']);
});
