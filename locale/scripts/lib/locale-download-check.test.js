const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const { execFileSync } = require('node:child_process');
const { emptyDownloads, integrity, modifiedLocales, regressions } = require('./locale-download-check');

test('an empty locale fails except the empty en_US base', () => {
    const files = [
        { name: 'en_US.json', size: 0 },
        { name: 'fr_FR.json', size: 0 },
        { name: 'de_DE.json', size: 900 },
    ];
    assert.deepEqual(emptyDownloads(files), ['fr_FR.json']);
});

test('integrity reads a directory: none, empty and healthy', () => {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'i18n-'));
    try {
        assert.equal(integrity(dir).length, 1);
        fs.writeFileSync(path.join(dir, 'en_US.json'), '');
        fs.writeFileSync(path.join(dir, 'fr_FR.json'), '{"a":"b"}');
        assert.deepEqual(integrity(dir), []);
        fs.writeFileSync(path.join(dir, 'de_DE.json'), '');
        assert.match(integrity(dir)[0], /de_DE\.json/);
    } finally {
        fs.rmSync(dir, { recursive: true, force: true });
    }
});

test('a regression is a substantial file that became tiny', () => {
    const files = [
        { name: 'a.json', now: 10, before: 900 },
        { name: 'b.json', now: 10, before: 400 },
        { name: 'c.json', now: 800, before: 900 },
    ];
    assert.deepEqual(regressions(files).map((f) => f.name), ['a.json']);
});

test('a download that empties a committed locale is measured against the checked-out commit', () => {
    const repo = fs.mkdtempSync(path.join(os.tmpdir(), 'i18n-repo-'));
    // Git hooks export GIT_DIR and GIT_INDEX_FILE, which would point these commands at the outer repo.
    const env = { ...process.env };
    delete env.GIT_DIR;
    delete env.GIT_INDEX_FILE;
    delete env.GIT_WORK_TREE;
    const git = (...args) => execFileSync('git', args, { cwd: repo, env, stdio: 'ignore' });
    try {
        const dir = path.join(repo, 'src/locale/i18n');
        fs.mkdirSync(dir, { recursive: true });
        fs.writeFileSync(path.join(dir, 'fr_FR.json'), JSON.stringify({ k: 'x'.repeat(900) }));
        git('init', '-q');
        git('add', '.');
        git('-c', 'user.name=t', '-c', 'user.email=t@t', 'commit', '-q', '-m', 'locale');
        fs.writeFileSync(path.join(dir, 'fr_FR.json'), '{}');
        assert.deepEqual(regressions(modifiedLocales(repo)).map((f) => f.name), ['src/locale/i18n/fr_FR.json']);
    } finally {
        fs.rmSync(repo, { recursive: true, force: true });
    }
});

test('a locale larger than the default exec buffer is measured by blob size', () => {
    const repo = fs.mkdtempSync(path.join(os.tmpdir(), 'i18n-repo-'));
    const env = { ...process.env };
    delete env.GIT_DIR;
    delete env.GIT_INDEX_FILE;
    delete env.GIT_WORK_TREE;
    const git = (...args) => execFileSync('git', args, { cwd: repo, env, stdio: 'ignore' });
    try {
        const dir = path.join(repo, 'src/locale/i18n');
        fs.mkdirSync(dir, { recursive: true });
        fs.writeFileSync(path.join(dir, 'ta_IN.json'), JSON.stringify({ k: 'x'.repeat(1024 * 1024) }));
        git('init', '-q');
        git('add', '.');
        git('-c', 'user.name=t', '-c', 'user.email=t@t', 'commit', '-q', '-m', 'locale');
        fs.writeFileSync(path.join(dir, 'ta_IN.json'), '{}');
        const [file] = modifiedLocales(repo);
        assert.equal(file.name, 'src/locale/i18n/ta_IN.json');
        assert.ok(file.before > 1024 * 1024);
        assert.equal(file.now, 2);
    } finally {
        fs.rmSync(repo, { recursive: true, force: true });
    }
});
