const { test } = require('node:test');
const assert = require('node:assert/strict');

const { planTermSync, defaultDeletionLimit } = require('./poeditor-terms');

const PO = `msgid ""
msgstr ""
"Plural-Forms: nplurals=INTEGER; plural=EXPRESSION;\\n"

msgid "Save"
msgstr ""

msgid "Cancel"
msgstr ""

msgid "Family"
msgid_plural "Families"
msgstr[0] ""
msgstr[1] ""

msgctxt "one"
msgid "Copied {{count}} members"
msgstr ""
`;

const remote = (...terms) => terms.map(([term, context = '', plural = '']) => ({ term, context, plural }));

test('plans nothing when POEditor already matches messages.po', () => {
    const plan = planTermSync(PO, remote(['Save'], ['Cancel'], ['Family', '', 'Families'], ['Copied {{count}} members', 'one']));
    assert.deepEqual(plan.added, []);
    assert.deepEqual(plan.removed, []);
    assert.equal(plan.blockedReason, null);
});

test('reports added and removed terms and ignores the PO header', () => {
    const plan = planTermSync(PO, remote(['Save'], ['Cancel'], ['Old label']));
    assert.deepEqual(plan.added.map(t => t.term), ['Family', 'Copied {{count}} members']);
    assert.deepEqual(plan.removed.map(t => t.term), ['Old label']);
    assert.equal(plan.blockedReason, null);
});

test('treats the same text in a different context as a different term', () => {
    const plan = planTermSync(PO, remote(['Save'], ['Cancel'], ['Family'], ['Copied {{count}} members', 'other']));
    assert.deepEqual(plan.added.map(t => t.context), ['one']);
    assert.deepEqual(plan.removed.map(t => t.context), ['other']);
});

test('blocks an empty messages.po', () => {
    const plan = planTermSync('msgid ""\nmsgstr ""\n', remote(['Save'], ['Cancel']));
    assert.match(plan.blockedReason, /no terms/);
});

test('blocks a sync that deletes more than the limit', () => {
    const many = Array.from({ length: 40 }, (_, i) => [`Old ${i}`]);
    const plan = planTermSync(PO, remote(['Save'], ...many));
    assert.equal(plan.removed.length, 40);
    assert.match(plan.blockedReason, /would delete 40 terms \(limit 25\)/);
});

test('the limit grows with the project and can be overridden for a deliberate cleanup', () => {
    assert.equal(defaultDeletionLimit(100), 25);
    assert.equal(defaultDeletionLimit(4000), 80);
    const many = Array.from({ length: 40 }, (_, i) => [`Old ${i}`]);
    assert.equal(planTermSync(PO, remote(['Save'], ...many), 50).blockedReason, null);
    assert.match(planTermSync(PO, remote(['Save'], ...many), 10).blockedReason, /limit 10/);
});

test('a term that gains, changes or loses its plural is an update, not a no-op', () => {
    const gained = planTermSync(PO, remote(['Save'], ['Cancel'], ['Family'], ['Copied {{count}} members', 'one']));
    assert.deepEqual(gained.updated.map(t => [t.term, t.plural, t.previousPlural]), [['Family', 'Families', '']]);
    const changed = planTermSync(PO, remote(['Save'], ['Cancel'], ['Family', '', 'Kin'], ['Copied {{count}} members', 'one']));
    assert.deepEqual(changed.updated.map(t => t.previousPlural), ['Kin']);
    const lost = planTermSync(PO.replace('msgid_plural "Families"\n', '').replace('msgstr[1] ""\n', ''),
        remote(['Save'], ['Cancel'], ['Family', '', 'Families'], ['Copied {{count}} members', 'one']));
    assert.deepEqual(lost.updated.map(t => [t.plural, t.previousPlural]), [['', 'Families']]);
    assert.equal(gained.added.length + gained.removed.length, 0);
});
