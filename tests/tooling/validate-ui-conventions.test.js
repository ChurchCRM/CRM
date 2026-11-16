const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { test } = require('node:test');

const root = path.resolve(__dirname, '../..');

test('checks source violations while ignoring runtime logs, non-source files and symlinks', (t) => {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'ui-conventions-'));
    t.after(() => fs.rmSync(dir, { recursive: true, force: true }));
    for (const sub of ['scripts/lint', 'src/logs', 'webpack']) fs.mkdirSync(path.join(dir, sub), { recursive: true });
    const script = path.join(dir, 'scripts/lint/validate-ui-conventions.js');
    fs.copyFileSync(path.join(root, 'scripts/lint/validate-ui-conventions.js'), script);
    fs.writeFileSync(path.join(dir, 'src/logs/runtime.php'), '<div class="ml-2">');
    fs.writeFileSync(path.join(dir, 'src/sample.log'), '<div class="ml-2">');
    fs.symlinkSync(path.join(dir, 'missing.php'), path.join(dir, 'src/broken.php'));
    const run = () => spawnSync(process.execPath, [script], { encoding: 'utf8' });
    let result = run();
    assert.equal(result.status, 0, result.stderr);
    fs.writeFileSync(path.join(dir, 'src/page.php'), '<div class="ml-2">');
    result = run();
    assert.equal(result.status, 1, result.stdout + result.stderr);
    assert.match(result.stderr, /src\/page.php:1 Bootstrap 4 class name/);
});
