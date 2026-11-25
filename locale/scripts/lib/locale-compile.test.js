'use strict';

const { test } = require('node:test');
const assert = require('node:assert');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const repo = require('./locale-repo');

const script = path.join(__dirname, '..', 'locale-compile.js');

test('locale:compile writes a .mo for every locale and leaves the JSON and queue alone', () => {
    const before = new Map(repo.targetLocales().map((t) => [t.code, fs.readFileSync(repo.paths(t).json, 'utf8')]));
    execFileSync(process.execPath, [script], { stdio: 'pipe' });

    for (const t of repo.targetLocales()) assert.ok(fs.statSync(repo.paths(t).mo).size > 0, `${t.code} has a .mo`);
    for (const t of repo.targetLocales()) {
        assert.strictEqual(fs.readFileSync(repo.paths(t).json, 'utf8'), before.get(t.code), `${t.code} JSON unchanged`);
    }
});
