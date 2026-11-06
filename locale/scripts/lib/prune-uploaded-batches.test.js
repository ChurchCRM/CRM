const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const os = require('os');
const path = require('path');

const { pruneUploadedBatches } = require('./prune-uploaded-batches');

const setup = (batch) => {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'prune-'));
    const sub = path.join(dir, 'en');
    fs.mkdirSync(sub);
    fs.writeFileSync(path.join(sub, 'en-1.json'), JSON.stringify(batch));
    return sub;
};

test('terms POEditor already holds are dropped, the rest stay', () => {
    const dir = setup({ Color: 'Colour', Center: 'Centre' });
    const result = pruneUploadedBatches(dir, { Color: 'Colour' });
    assert.deepEqual(result, { removed: 1, files: 0 });
    assert.deepEqual(JSON.parse(fs.readFileSync(path.join(dir, 'en-1.json'), 'utf8')), { Center: 'Centre' });
});

test('a fully uploaded batch deletes the file and the empty folder', () => {
    const dir = setup({ Color: 'Colour' });
    assert.deepEqual(pruneUploadedBatches(dir, { Color: 'Colour' }), { removed: 1, files: 1 });
    assert.equal(fs.existsSync(dir), false);
});

test('a different value in POEditor keeps the term queued', () => {
    const dir = setup({ Color: 'Colour' });
    assert.deepEqual(pruneUploadedBatches(dir, { Color: 'Color' }), { removed: 0, files: 0 });
    assert.equal(fs.existsSync(path.join(dir, 'en-1.json')), true);
});
