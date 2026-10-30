const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { validateComposerLock } = require('./validate-composer-lock');

test('checks the staged Composer pair even when the working copy is fixed', () => {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'composer-lock-test-'));
    const git = (...args) => execFileSync('git', args, { cwd: root, stdio: 'pipe' });
    try {
        fs.mkdirSync(path.join(root, 'src'));
        for (const file of ['composer.json', 'composer.lock']) {
            fs.copyFileSync(path.join(__dirname, '..', 'src', file), path.join(root, 'src', file));
        }
        git('init', '-q');
        assert.equal(validateComposerLock(root, true), 0);
        assert.equal(validateComposerLock(root), 0);
        git('add', 'src/composer.json', 'src/composer.lock');
        assert.equal(validateComposerLock(root, true), 0);

        const file = path.join(root, 'src/composer.json');
        const original = fs.readFileSync(file, 'utf8');
        const json = JSON.parse(original);
        json.require['mpdf/mpdf'] = '^99.0';
        fs.writeFileSync(file, JSON.stringify(json));
        git('add', 'src/composer.json');
        fs.writeFileSync(file, original);
        assert.equal(validateComposerLock(root), 0);
        assert.notEqual(validateComposerLock(root, true), 0);

        git('add', 'src/composer.json');
        assert.equal(validateComposerLock(root, true), 0);
    } finally {
        fs.rmSync(root, { recursive: true, force: true });
    }
});
