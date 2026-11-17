'use strict';

const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const os = require('os');
const path = require('path');

const { findTermLocations, buildContext, writeContext } = require('./locale-context');

const grep = (fragment) => (fragment.includes('Latest Notes')
    ? 'src/PersonView.php:210: echo gettext("Latest Notes");\nsrc/PersonView.php:210: duplicate hit\nsrc/Notes.php:12: "Latest Notes"\nsrc/Other.php:3: x\nsrc/Fourth.php:4: x\n'
    : '');

test('findTermLocations returns deduped path:line locations, capped', () => {
    assert.deepEqual(findTermLocations('Latest Notes', grep), ['src/PersonView.php:210', 'src/Notes.php:12', 'src/Other.php:3']);
    assert.deepEqual(findTermLocations('Not in the code', grep), []);
});

test('buildContext keeps only terms with locations', () => {
    assert.deepEqual(buildContext(['Latest Notes', 'Not in the code'], grep), {
        'Latest Notes': ['src/PersonView.php:210', 'src/Notes.php:12', 'src/Other.php:3'],
    });
});

test('writeContext writes the file, and removes it when nothing has a location', () => {
    const file = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'locale-context-')), 'context.json');
    assert.equal(writeContext(file, ['Latest Notes'], grep), 1);
    assert.deepEqual(JSON.parse(fs.readFileSync(file, 'utf8')), {
        'Latest Notes': ['src/PersonView.php:210', 'src/Notes.php:12', 'src/Other.php:3'],
    });
    assert.equal(writeContext(file, ['Not in the code'], grep), 0);
    assert.equal(fs.existsSync(file), false);
});
