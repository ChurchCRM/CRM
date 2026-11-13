const { test } = require('node:test');
const assert = require('node:assert/strict');

const files = require('./locale-files');
const { compileMo } = require('./locale-repo');

const SOURCE = [
    'msgid ""',
    'msgstr ""',
    '',
    'msgid "Delete"',
    'msgstr ""',
    '',
    'msgctxt "one"',
    'msgid "{{count}} files"',
    'msgstr ""',
    '',
    'msgctxt "other"',
    'msgid "{{count}} files"',
    'msgstr ""',
    '',
    'msgid "%d month old"',
    'msgid_plural "%d months old"',
    'msgstr[0] ""',
    'msgstr[1] ""',
    '',
    'msgid "Line one\\nLine two"',
    'msgstr ""',
    '',
].join('\n');

const entries = files.sourceEntries(SOURCE);
const header = files.poHeader('ru', 3);

test('term shapes follow messages.po and the locale plural forms', () => {
    const shapes = files.termShapes(entries, ['one', 'few', 'other']);
    assert.deepEqual(shapes.get('Delete'), { kind: 'plain' });
    assert.deepEqual(shapes.get('{{count}} files'), { kind: 'context', forms: ['one', 'other'] });
    assert.deepEqual(shapes.get('%d month old'), { kind: 'plural', forms: ['one', 'few', 'other'] });
});

test('plural forms come from the locale JSON, falling back to CLDR', () => {
    assert.deepEqual(files.localePluralForms(entries, { '%d month old': { one: 'a', other: 'b' } }, 'pl'), ['one', 'other']);
    assert.deepEqual(files.localePluralForms(entries, {}, 'ru'), ['one', 'few', 'many', 'other']);
    assert.deepEqual(files.localePluralForms(entries, {}, 'ja'), ['other']);
});

test('missing terms are listed in the batch shape, keeping filled plural forms', () => {
    const shapes = files.termShapes(entries, ['one', 'other']);
    const missing = files.findMissing(shapes, { Delete: 'Удалить', '%d month old': { one: '%d месяц', other: '' } });
    assert.deepEqual(missing, {
        '{{count}} files': { one: '', other: '' },
        '%d month old': { one: '%d месяц', other: '' },
        'Line one\nLine two': '',
    });
});

test('obsolete terms are the JSON keys messages.po no longer has', () => {
    const shapes = files.termShapes(entries, ['one', 'other']);
    assert.deepEqual(files.obsoleteTerms(shapes, { Delete: 'x', Gone: 'y' }), ['Gone']);
});

test('the .po is written from the JSON, in POEditor layout', () => {
    const shapes = files.termShapes(entries, ['one', 'other']);
    const json = {
        Delete: 'Удалить',
        '{{count}} files': { one: '{{count}} файл', other: '{{count}} файлов' },
        '%d month old': { one: '%d месяц', other: '%d месяцев' },
        'Line one\nLine two': 'Строка "один"\nСтрока два',
    };
    const { text, added } = files.renderLocalePo('', entries, shapes, json, header);
    assert.equal(added, 5);
    assert.match(text, /#: \nmsgid "Delete"\nmsgstr "Удалить"\n/);
    assert.match(text, /msgctxt "other"\nmsgid "\{\{count\}\} files"\nmsgstr "\{\{count\}\} файлов"/);
    assert.match(text, /msgid_plural "%d months old"\nmsgstr\[0\] "%d месяц"\nmsgstr\[1\] "%d месяцев"/);
    assert.match(text, /msgid "Line one\\n"\n"Line two"\nmsgstr "Строка \\"один\\"\\n"\n"Строка два"/);
    assert.ok(text.endsWith('\n\n'));

    const again = files.renderLocalePo(text, entries, shapes, json, header);
    assert.equal(again.changed, false, 'rendering is stable');

    const mo = compileMo(text);
    assert.ok(mo.length > 0);
});

test('unchanged entries keep their text; changed, untranslated and obsolete ones are handled', () => {
    const shapes = files.termShapes(entries, ['one', 'other']);
    const existing = [
        header,
        '#. kept comment\n#: \n#, fuzzy\nmsgid "Delete"\nmsgstr "Удалить"',
        '#: \nmsgid "Gone"\nmsgstr "Нет"',
        '#: \nmsgid "Line one\\n"\n"Line two"\nmsgstr "old"',
    ].join('\n\n') + '\n\n';
    const json = { Delete: 'Удалить', 'Line one\nLine two': 'new' };
    const r = files.renderLocalePo(existing, entries, shapes, json, header);
    assert.deepEqual([r.added, r.updated, r.removed], [0, 1, 1]);
    assert.match(r.text, /#\. kept comment\n#: \n#, fuzzy\nmsgid "Delete"/, 'an unchanged fuzzy entry is left as it was');
    assert.doesNotMatch(r.text, /Gone/);
    assert.match(r.text, /msgid "Line one\\n"\n"Line two"\nmsgstr "new"/);
});

test('a term without a complete translation stays out of the .po', () => {
    const shapes = files.termShapes(entries, ['one', 'other']);
    const r = files.renderLocalePo('', entries, shapes, { '%d month old': { one: 'x', other: '' }, Delete: '' }, header);
    assert.equal(r.added, 0);
});
