const test = require('node:test');
const assert = require('node:assert');
const { needsRereview, MARKER } = require('./coderabbit-rereview');

const review = (state, at, login = 'coderabbitai') => ({ author: { login }, state, submittedAt: at });
const resolved = { isResolved: true };
const open = { isResolved: false };
const base = { reviews: [review('CHANGES_REQUESTED', '2026-10-29T10:00:00Z')], threads: [resolved], comments: [] };

test('asks again when CodeRabbit blocks and every thread is resolved', () => {
    assert.ok(needsRereview(base));
});

test('waits while a thread is open, or when there are no threads', () => {
    assert.ok(!needsRereview({ ...base, threads: [resolved, open] }));
    assert.ok(!needsRereview({ ...base, threads: [] }));
});

test('a later COMMENTED review does not hide the blocking state', () => {
    const reviews = [review('CHANGES_REQUESTED', '2026-10-29T10:00:00Z'), review('COMMENTED', '2026-10-29T11:00:00Z')];
    assert.ok(needsRereview({ ...base, reviews }));
});

test('an approval after the block clears it', () => {
    const reviews = [review('CHANGES_REQUESTED', '2026-10-29T10:00:00Z'), review('APPROVED', '2026-10-29T12:00:00Z')];
    assert.ok(!needsRereview({ ...base, reviews }));
});

test('ignores other reviewers', () => {
    assert.ok(!needsRereview({ ...base, reviews: [review('CHANGES_REQUESTED', '2026-10-29T10:00:00Z', 'someone')] }));
});

test('asks once per blocking review', () => {
    const asked = [{ body: `x ${MARKER}`, createdAt: '2026-10-29T10:30:00Z' }];
    assert.ok(!needsRereview({ ...base, comments: asked }));
    const stale = [{ body: `x ${MARKER}`, createdAt: '2026-10-29T09:00:00Z' }];
    assert.ok(needsRereview({ ...base, comments: stale }));
});
