'use strict';

const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const os = require('os');
const path = require('path');

const { applyBatches, flushPendingWrites } = require('../locale-apply');

function makeCtx(dir) {
    return {
        paths: { batches: dir },
        shapes: new Map([
            ['Latest Notes', { kind: 'plain' }],
            ['No notes have been added yet.', { kind: 'plain' }],
        ]),
        translations: {},
        target: { code: 'de', scripts: ['Latin'] },
    };
}

function tempBatch(terms) {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'locale-apply-'));
    const file = path.join(dir, 'de-1.json');
    fs.writeFileSync(file, `${JSON.stringify(terms, null, 2)}\n`, 'utf8');
    return { dir, file };
}

test('applyBatches leaves the batch file on disk untouched until the writes are flushed', () => {
    const { dir, file } = tempBatch({ 'Latest Notes': 'Neueste Notizen', 'No notes have been added yet.': '' });
    const before = fs.readFileSync(file, 'utf8');

    const ctx = makeCtx(dir);
    const result = applyBatches(ctx, { prune: false });

    assert.equal(result.applied, 1);
    assert.equal(result.kept, 1);
    assert.equal(ctx.translations['Latest Notes'], 'Neueste Notizen');
    // The filled value still exists only in the batch file at this point: a
    // failure writing the locale files must not lose it.
    assert.equal(fs.readFileSync(file, 'utf8'), before);

    flushPendingWrites(result.pendingWrites);
    assert.deepEqual(JSON.parse(fs.readFileSync(file, 'utf8')), { 'No notes have been added yet.': '' });
});

test('flushPendingWrites deletes a batch file whose terms all applied', () => {
    const { dir, file } = tempBatch({ 'Latest Notes': 'Neueste Notizen' });

    const result = applyBatches(makeCtx(dir), { prune: false });
    assert.equal(fs.existsSync(file), true);

    flushPendingWrites(result.pendingWrites);
    assert.equal(fs.existsSync(file), false);
});
