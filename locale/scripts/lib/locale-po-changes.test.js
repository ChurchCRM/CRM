const { test } = require('node:test');
const assert = require('node:assert/strict');

const { meaningfulChanges } = require('./locale-po-changes');

test('a diff of only header timestamps is not a change', () => {
    const diff = [
        '--- a/locale/messages.po',
        '+++ b/locale/messages.po',
        '-"POT-Creation-Date: 2026-01-01 00:00+0000\\n"',
        '+"POT-Creation-Date: 2026-01-02 00:00+0000\\n"',
        '-"PO-Revision-Date: 2026-01-01\\n"',
        '+"PO-Revision-Date: 2026-01-02\\n"',
    ].join('\n');
    assert.equal(meaningfulChanges(diff), 0);
});

test('a changed message counts, file headers do not', () => {
    const diff = ['--- a/x', '+++ b/x', '-msgid "Old"', '+msgid "New"', ' context'].join('\n');
    assert.equal(meaningfulChanges(diff), 2);
});
