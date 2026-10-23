const { describe, it, afterEach } = require('node:test');
const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const SCRIPTS = ['build.js', 'clean.js', 'composer-install.js', 'docker-config.js'];
const skip = process.platform === 'win32' && 'fake shell binaries need a POSIX shell';
const trees = [];

function makeTree() {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'crm-tooling-'));
    trees.push(root);
    fs.mkdirSync(path.join(root, 'scripts'));
    fs.mkdirSync(path.join(root, 'bin'));
    for (const name of SCRIPTS) {
        fs.copyFileSync(path.join(__dirname, name), path.join(root, 'scripts', name));
    }
    return root;
}

function write(root, relative, content) {
    const file = path.join(root, relative);
    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, content);
    return file;
}

function fakeBin(root, name, body) {
    fs.chmodSync(write(root, `bin/${name}`, `#!/bin/sh\n${body}\n`), 0o755);
}

function run(root, script, args = [], env = {}) {
    return spawnSync(process.execPath, [path.join(root, 'scripts', script), ...args], {
        cwd: root,
        encoding: 'utf8',
        env: { PATH: path.join(root, 'bin'), ...env },
    });
}

function lockWith(root, lockedReference, installedReference) {
    const pkg = (reference) => ({ name: 'vendor/pkg', version: '1.0.0', dist: { reference } });
    write(root, 'src/composer.lock', JSON.stringify({ packages: [pkg(lockedReference)] }));
    if (installedReference) {
        write(root, 'src/vendor/composer/installed.json', JSON.stringify({ dev: false, packages: [pkg(installedReference)] }));
    }
}

afterEach(() => {
    for (const root of trees.splice(0)) {
        fs.rmSync(root, { recursive: true, force: true });
    }
});

describe('composer-install.js', { skip }, () => {
    const logComposer = 'echo "$@" >> "$LOG"\nexit "${COMPOSER_EXIT:-0}"';

    it('skips without composer when vendor matches composer.lock', () => {
        const root = makeTree();
        lockWith(root, 'abc', 'abc');
        const result = run(root, 'composer-install.js');
        assert.equal(result.status, 0);
        assert.match(result.stdout, /skipping/);
    });

    it('fails with a message when an install is needed and composer is missing', () => {
        const root = makeTree();
        lockWith(root, 'abc');
        const result = run(root, 'composer-install.js');
        assert.equal(result.status, 1);
        assert.match(result.stderr, /composer is not installed/);
    });

    it('validates, installs, and strips test and doc folders', () => {
        const root = makeTree();
        const log = path.join(root, 'composer.log');
        fakeBin(root, 'composer', logComposer);
        lockWith(root, 'abc');
        write(root, 'src/vendor/pkg/tests/a.php', '');
        write(root, 'src/vendor/pkg/docs/readme.md', '');
        write(root, 'src/vendor/pkg/src/keep.php', '');
        const result = run(root, 'composer-install.js', [], { LOG: log });
        assert.equal(result.status, 0);
        assert.deepEqual(fs.readFileSync(log, 'utf8').trim().split('\n'), ['validate --no-check-publish', 'install --no-dev']);
        assert.ok(!fs.existsSync(path.join(root, 'src/vendor/pkg/tests')));
        assert.ok(!fs.existsSync(path.join(root, 'src/vendor/pkg/docs')));
        assert.ok(fs.existsSync(path.join(root, 'src/vendor/pkg/src/keep.php')));
    });

    it('reinstalls when a package is pinned to a different commit', () => {
        const root = makeTree();
        const log = path.join(root, 'composer.log');
        fakeBin(root, 'composer', logComposer);
        lockWith(root, 'abc', 'def');
        const result = run(root, 'composer-install.js', [], { LOG: log });
        assert.equal(result.status, 0);
        assert.match(fs.readFileSync(log, 'utf8'), /install --no-dev/);
    });

    it('passes composer failures through', () => {
        const root = makeTree();
        fakeBin(root, 'composer', logComposer);
        lockWith(root, 'abc');
        const result = run(root, 'composer-install.js', [], { LOG: path.join(root, 'composer.log'), COMPOSER_EXIT: '3' });
        assert.equal(result.status, 3);
    });
});

describe('build.js', { skip }, () => {
    const fakeNpm = '[ "$1" = run ] || exit 2\necho "$2" >> "$LOG"\n[ "$2" = "$FAIL" ] && exit 1\nexit 0';

    it('signs only after composer and webpack both finish', () => {
        const root = makeTree();
        const log = path.join(root, 'npm.log');
        fakeBin(root, 'npm', fakeNpm);
        const result = run(root, 'build.js', [], { LOG: log });
        assert.equal(result.status, 0);
        const steps = fs.readFileSync(log, 'utf8').trim().split('\n');
        assert.deepEqual(steps.slice(0, 2).sort(), ['build:frontend', 'build:php']);
        assert.equal(steps[2], 'build:signatures');
    });

    for (const failing of ['build:php', 'build:frontend']) {
        it(`fails without signing when ${failing} fails`, () => {
            const root = makeTree();
            const log = path.join(root, 'npm.log');
            fakeBin(root, 'npm', fakeNpm);
            const result = run(root, 'build.js', [], { LOG: log, FAIL: failing });
            assert.equal(result.status, 1);
            assert.ok(!fs.readFileSync(log, 'utf8').includes('build:signatures'));
        });
    }
});

describe('clean.js', () => {
    function populate(root) {
        write(root, 'node_modules/x/index.js', '');
        write(root, 'src/vendor/y.php', '');
        write(root, 'src/logs/app.log', '');
        write(root, 'src/logs/keep.txt', '');
    }

    it('--logs removes only .log files', () => {
        const root = makeTree();
        populate(root);
        assert.equal(run(root, 'clean.js', ['--logs']).status, 0);
        assert.ok(!fs.existsSync(path.join(root, 'src/logs/app.log')));
        assert.ok(fs.existsSync(path.join(root, 'src/logs/keep.txt')));
        assert.ok(fs.existsSync(path.join(root, 'node_modules')));
    });

    it('removes dependencies and logs', () => {
        const root = makeTree();
        populate(root);
        assert.equal(run(root, 'clean.js').status, 0);
        assert.ok(!fs.existsSync(path.join(root, 'node_modules')));
        assert.ok(!fs.existsSync(path.join(root, 'src/vendor')));
        assert.ok(!fs.existsSync(path.join(root, 'src/logs/app.log')));
        assert.ok(fs.existsSync(path.join(root, 'src/logs/keep.txt')));
    });
});

describe('docker-config.js', () => {
    const dockerConfig = "<?php\n$sPASSWORD = 'changeme';\n";
    const realConfig = "<?php\n$sPASSWORD = 'real-secret';\n";
    const backups = (root) => fs.readdirSync(path.join(root, 'src/Include')).filter((name) => name.endsWith('.bak'));

    it('replaces a docker config without a backup', () => {
        const root = makeTree();
        write(root, 'docker/Config.php', dockerConfig);
        write(root, 'src/Include/Config.php', "<?php\n$sPASSWORD = 'changeme'; // old\n");
        assert.equal(run(root, 'docker-config.js', ['docker/Config.php']).status, 0);
        assert.equal(fs.readFileSync(path.join(root, 'src/Include/Config.php'), 'utf8'), dockerConfig);
        assert.deepEqual(backups(root), []);
    });

    it('backs up a real config before replacing it', () => {
        const root = makeTree();
        write(root, 'docker/Config.php', dockerConfig);
        write(root, 'src/Include/Config.php', realConfig);
        assert.equal(run(root, 'docker-config.js', ['docker/Config.php']).status, 0);
        const [backup] = backups(root);
        assert.equal(fs.readFileSync(path.join(root, 'src/Include', backup), 'utf8'), realConfig);
        if (process.platform !== 'win32') {
            assert.equal(fs.statSync(path.join(root, 'src/Include', backup)).mode & 0o777, 0o600);
        }
        assert.equal(fs.readFileSync(path.join(root, 'src/Include/Config.php'), 'utf8'), dockerConfig);
    });

    it('--remove backs up a real config and deletes it', () => {
        const root = makeTree();
        write(root, 'src/Include/Config.php', realConfig);
        assert.equal(run(root, 'docker-config.js', ['--remove']).status, 0);
        assert.ok(!fs.existsSync(path.join(root, 'src/Include/Config.php')));
        assert.equal(backups(root).length, 1);
    });

    it('prints usage without an argument', () => {
        const root = makeTree();
        const result = run(root, 'docker-config.js');
        assert.equal(result.status, 1);
        assert.match(result.stderr, /usage/);
    });
});
