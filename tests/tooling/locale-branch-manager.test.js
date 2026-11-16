const assert = require('node:assert/strict');
const { execFileSync, spawnSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { test } = require('node:test');

const root = path.resolve(__dirname, '../..');
const fixtureEnv = Object.fromEntries(Object.entries(process.env).filter(([key]) => !key.startsWith('GIT_')));

function fixture(t) {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'locale-checkpoint-'));
    t.after(() => fs.rmSync(dir, { recursive: true, force: true }));
    const cwd = path.join(dir, 'repo');
    fs.mkdirSync(cwd);
    fs.cpSync(path.join(root, 'locale/scripts'), path.join(cwd, 'locale/scripts'), { recursive: true });
    fs.symlinkSync(path.join(root, 'node_modules'), path.join(cwd, 'node_modules'), 'dir');
    const write = (name, text) => {
        const file = path.join(cwd, name);
        fs.mkdirSync(path.dirname(file), { recursive: true });
        fs.writeFileSync(file, text);
    };
    write('.gitignore', 'node_modules/\n');
    write('package.json', '{"version":"1.0.0"}');
    write('locale/messages.po', 'msgid "Hello"\nmsgstr ""\n\nmsgid "Total: %d"\nmsgstr ""\n');
    write('locale/terms/english-ok.json', '{}\n');
    write('src/locale/locales.json', JSON.stringify({ French: { poEditor: 'fr', locale: 'fr_FR', languageCode: 'fr', scripts: ['Latin'] } }));
    write('src/locale/i18n/fr_FR.json', '{}\n');
    write('locale/terms/missing/fr/fr-1.json', '{"Hello":"Bonjour"}\n');
    const git = (...args) => execFileSync('git', args, { cwd, env: fixtureEnv, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
    git('init', '-b', 'master');
    git('config', 'user.email', 'test@example.com');
    git('config', 'user.name', 'Test');
    git('config', 'core.hooksPath', path.join(dir, 'hooks'));
    git('add', '--', '.gitignore', 'package.json', 'locale', 'src');
    git('commit', '-m', 'Seed');
    const remote = path.join(dir, 'remote.git');
    execFileSync('git', ['init', '--bare', remote], { env: fixtureEnv, stdio: 'ignore' });
    git('remote', 'add', 'origin', remote);
    const cli = (...args) => spawnSync(process.execPath, ['locale/scripts/locale-branch-manager.js', ...args], { cwd, env: fixtureEnv, encoding: 'utf8' });
    const checkpoint = () => cli('--commit-and-push', '--locale', 'fr', '--language', 'French', '--terms', '1');
    return { cwd, dir, write, git, remote, cli, checkpoint };
}

function ok(result) {
    assert.equal(result.status, 0, result.stdout + result.stderr);
}

test('checkpoints only the locale and backs it up remotely, including its emptied queue', (t) => {
    const f = fixture(t);
    ok(f.cli('--init'));
    f.write('unrelated.txt', 'Keep local\n');
    ok(f.checkpoint());
    const files = f.git('show', '--pretty=', '--name-only', 'HEAD');
    assert.match(files, /src\/locale\/i18n\/fr_FR.json/);
    assert.match(files, /messages.po/);
    assert.match(files, /messages.mo/);
    assert.match(files, /locale\/terms\/missing\/fr\/fr-1.json/);
    assert.doesNotMatch(files, /unrelated/);
    assert.equal(f.git('rev-parse', 'HEAD'), f.git('rev-parse', '@{u}'));
    const sha = f.git('rev-parse', 'HEAD');
    ok(f.checkpoint());
    assert.equal(f.git('rev-parse', 'HEAD'), sha);
});

test('retries a failed push from the local checkpoint without requiring its deleted queue', (t) => {
    const f = fixture(t);
    ok(f.cli('--init'));
    f.git('remote', 'set-url', 'origin', path.join(f.dir, 'unavailable.git'));
    assert.notEqual(f.checkpoint().status, 0);
    const sha = f.git('rev-parse', 'HEAD');
    assert.match(f.cli('--get-translated').stdout, /"fr"/);
    f.git('remote', 'set-url', 'origin', f.remote);
    ok(f.checkpoint());
    assert.equal(f.git('rev-parse', 'HEAD'), sha);
    assert.equal(f.git('rev-parse', '@{u}'), sha);
});

test('retries a failed commit hook with the locale files already staged', (t) => {
    const f = fixture(t);
    ok(f.cli('--init'));
    const hook = path.join(f.dir, 'hooks/pre-commit');
    fs.mkdirSync(path.dirname(hook));
    fs.writeFileSync(hook, '#!/bin/sh\nexit 1\n', { mode: 0o755 });
    assert.notEqual(f.checkpoint().status, 0);
    fs.writeFileSync(hook, '#!/bin/sh\nexit 0\n');
    ok(f.checkpoint());
    assert.equal(f.git('rev-parse', 'HEAD'), f.git('rev-parse', '@{u}'));
});

test('protects master and unrelated staged work before applying translations', (t) => {
    const f = fixture(t);
    const before = fs.readFileSync(path.join(f.cwd, 'src/locale/i18n/fr_FR.json'), 'utf8');
    assert.notEqual(f.checkpoint().status, 0);
    ok(f.cli('--init'));
    f.write('unrelated.txt', 'Keep staged\n');
    f.git('add', '--', 'unrelated.txt');
    const result = f.checkpoint();
    assert.notEqual(result.status, 0);
    assert.match(result.stderr, /unrelated staged files/);
    assert.equal(fs.readFileSync(path.join(f.cwd, 'src/locale/i18n/fr_FR.json'), 'utf8'), before);
    assert.equal(f.git('diff', '--cached', '--name-only'), 'unrelated.txt');
});

test('keeps rejected values in the batch without committing or pushing them', (t) => {
    const f = fixture(t);
    ok(f.cli('--init'));
    const sha = f.git('rev-parse', 'HEAD');
    f.write('locale/terms/missing/fr/fr-1.json', '{"Total: %d":"Total"}\n');
    assert.notEqual(f.checkpoint().status, 0);
    assert.match(fs.readFileSync(path.join(f.cwd, 'locale/terms/missing/fr/fr-1.json'), 'utf8'), /Total/);
    assert.equal(f.git('rev-parse', 'HEAD'), sha);
    assert.equal(f.git('rev-parse', '@{u}'), sha);
});

test('pre-push clears inherited repository paths before lint starts', (t) => {
    const f = fixture(t);
    const bin = path.join(f.dir, 'bin');
    fs.mkdirSync(bin);
    fs.writeFileSync(path.join(bin, 'npm'), `#!${process.execPath}
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
assert.equal(process.env.GIT_DIR, undefined);
assert.equal(process.env.GIT_INDEX_FILE, undefined);
assert.equal(execFileSync('git', ['rev-parse', '--show-toplevel'], { encoding: 'utf8' }).trim(), process.cwd());
`, { mode: 0o755 });
    const result = spawnSync('bash', [path.join(root, '.githooks/pre-push')], {
        cwd: f.cwd,
        encoding: 'utf8',
        env: { ...fixtureEnv, CI: '', GITHUB_ACTIONS: '', PATH: bin + path.delimiter + process.env.PATH, GIT_DIR: f.git('rev-parse', '--absolute-git-dir'), GIT_INDEX_FILE: path.join(f.dir, 'hook-index') },
    });
    ok(result);
    assert.match(result.stdout, /pre-push: lint passed/);
});
