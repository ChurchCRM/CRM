const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { test } = require('node:test');
const { options, run } = require('./test-php');

function fixture(t) {
    const repo = fs.mkdtempSync(path.join(os.tmpdir(), 'churchcrm-runner-'));
    t.after(() => fs.rmSync(repo, { recursive: true, force: true }));
    for (const file of ['tests/php/vendor/bin/tester', 'tests/php/unit/Service/ExampleTest.php', 'tests/php/integration/Controller/ExampleTest.php', 'src/vendor/autoload.php', 'src/Include/Config.php', 'src/mysql/upgrade.json']) {
        fs.mkdirSync(path.dirname(path.join(repo, file)), { recursive: true });
        fs.writeFileSync(path.join(repo, file), file);
    }
    return repo;
}

test('selects a package and rejects escaping or misspelled targets', (t) => {
    const repo = fixture(t);
    assert.equal(options(['unit', 'Service', '--junit'], repo).junit, true);
    for (const args of [[], ['all'], ['unit', '../integration'], ['unit', 'missing'], ['unit', '--stop-on-fail'], ['unit', 'Service', 'Service']]) {
        assert.throws(() => options(args, repo));
    }
});

test('preserves test failures, requests serial execution and writes JUnit', (t) => {
    const repo = fixture(t);
    const status = run(options(['unit', '--junit'], repo), { repo, env: {}, spawn: (bin, args, config) => {
        assert.equal(bin, 'php');
        assert.equal(args[args.indexOf('-j') + 1], '1');
        assert.ok(args.includes(`junit:${path.join(repo, 'temp/php-tests/unit.xml')}`));
        assert.equal(config.env.PHP_TEST_APP_ROOT, path.join(repo, 'src'));
        return { status: 1 };
    } });
    assert.equal(status, 1);
});

test('integration requires an explicit disposable database', (t) => {
    const repo = fixture(t);
    assert.throws(() => run(options(['integration'], repo), { repo, env: {}, spawn: () => assert.fail('must not spawn') }), /DISPOSABLE/);
});

test('integration mutates only an isolated copy and cleans it after failure', (t) => {
    const repo = fixture(t);
    let isolatedRoot;
    assert.throws(() => run(options(['integration'], repo), {
        repo,
        env: { PHP_TEST_DISPOSABLE_DB: '1', UPGRADE_TEST_DSN: 'mysql:host=localhost', UPGRADE_TEST_USER: 'test', UPGRADE_TEST_PASSWORD: 'test' },
        spawn: (bin, args, { env }) => {
            isolatedRoot = env.PHP_TEST_APP_ROOT;
            assert.equal(env.PHP_TEST_ISOLATED, '1');
            assert.notEqual(isolatedRoot, path.join(repo, 'src'));
            assert.equal(fs.existsSync(path.join(isolatedRoot, 'Include/Config.php')), false);
            fs.writeFileSync(path.join(isolatedRoot, 'mysql/upgrade.json'), 'changed');
            throw new Error('test failure');
        },
    }), /test failure/);
    assert.equal(fs.existsSync(isolatedRoot), false);
    assert.equal(fs.readFileSync(path.join(repo, 'src/mysql/upgrade.json'), 'utf8'), 'src/mysql/upgrade.json');
    assert.equal(fs.existsSync(path.join(repo, 'src/Include/Config.php')), true);
});
