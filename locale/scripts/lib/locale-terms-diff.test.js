const { test } = require('node:test');
const assert = require('node:assert/strict');

const { diffTerms, suspicions, similar, renderReport, truncateBody, title, searchFragment } = require('./locale-terms-diff');

const po = (...ids) => ['msgid ""', 'msgstr ""', '"POT-Creation-Date: 2026-01-01\\n"', '',
    ...ids.flatMap(id => [`msgid ${JSON.stringify(id)}`, 'msgstr ""', ''])].join('\n');

test('lists added and removed msgids, ignoring the header', () => {
    const d = diffTerms(po('Save', 'Old Label'), po('Save', 'New Label', 'Delete'));
    assert.deepEqual(d.added, ['Delete', 'New Label']);
    assert.deepEqual(d.removed, ['Old Label']);
    assert.equal(title(d, '2026-11-11'), 'locale: new terms from code (+2 / -1) - 2026-11-11');
});

test('i18next one/other pairs are one row', () => {
    const base = po('Save');
    const head = `${base}\nmsgctxt "one"\nmsgid "{{count}} items"\nmsgstr ""\n\nmsgctxt "other"\nmsgid "{{count}} items"\nmsgstr ""\n`;
    const d = diffTerms(base, head);
    assert.deepEqual(d.added, ['{{count}} items']);
    assert.deepEqual([...d.head.get('{{count}} items').contexts], ['one', 'other']);
});

test('flags strings that look like bugs', () => {
    const all = new Map([['Save Changes', {}]]);
    const why = s => suspicions(s, all).join('; ');
    assert.match(why(' Save'), /whitespace/);
    assert.match(why('123 / 456'), /no letters/);
    assert.match(why('<b>Bold</b>'), /HTML/);
    assert.match(why('sPersonName'), /code/);
    assert.match(why('user_id'), /code/);
    assert.match(why('$row->name'), /code/);
    assert.match(why('Hello {{name}'), /placeholder/);
    assert.match(why('save changes.'), /near-duplicate of existing “Save Changes”/);
    assert.match(why('x'.repeat(250)), /long/);
    assert.equal(why('Delete %s from {{group}}'), '');
    assert.equal(why('Family Name'), '');
});

test('detects a reworded term that would orphan translations', () => {
    assert.ok(similar('PHP version', 'PHP version (Errors and Full)'));
    assert.ok(similar('Canceled', 'Cancelled'));
    assert.ok(!similar('Save', 'Sale'));
    const d = diffTerms(po('Organisation Name'), po('Organization Name'));
    assert.deepEqual(d.renames, [{ from: 'Organisation Name', to: 'Organization Name' }]);
    assert.match(renderReport(d), /Needs a look \(1\)/);
});

test('body escapes table cells and stays under the limit', () => {
    const many = Array.from({ length: 2000 }, (_, i) => `Term number ${i} with | pipe and <tag> text that is fairly long`);
    const d = diffTerms(po(), po(...many));
    const full = renderReport(d);
    assert.ok(full.includes('\\|') && full.includes('&lt;tag>'));
    const body = truncateBody(full, 'https://example.test/run');
    assert.ok(body.length <= 60000);
    assert.match(body, /Truncated/);
    assert.equal((body.match(/<details>/g) || []).length, (body.match(/<\/details>/g) || []).length);
});

test('search fragment skips quotes and placeholders', () => {
    assert.equal(searchFragment('"%s" is not a kind of calendar'), 'is not a kind of calendar');
    assert.equal(searchFragment('{{count}}'), '');
});
