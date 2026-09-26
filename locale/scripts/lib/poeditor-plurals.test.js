const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const {
    parsePoEntries,
    loadSourceTermKinds,
    repairJoinedPlural,
    buildPoeditorPayload,
} = require('./poeditor-plurals');

const SOURCE_PO = `msgid ""
msgstr ""
"Plural-Forms: nplurals=INTEGER; plural=EXPRESSION;\\n"

#, php-format
msgid "Email sent to %d family."
msgid_plural "Emails sent to %d families."
msgstr[0] ""
msgstr[1] ""

msgctxt "one"
msgid "Copied {{count}} members"
msgstr ""

msgctxt "other"
msgid "Copied {{count}} members"
msgstr ""

msgid ""
"Long term "
"split over lines"
msgstr ""

msgid "Save"
msgstr ""
`;

const kinds = loadSourceTermKinds(SOURCE_PO);

test('parsePoEntries reads context, plural and continuation lines', () => {
    const entries = parsePoEntries(SOURCE_PO);
    assert.deepEqual(entries.find((e) => e.msgid === 'Email sent to %d family.').msgidPlural, 'Emails sent to %d families.');
    assert.equal(entries.filter((e) => e.msgid === 'Copied {{count}} members').length, 2);
    assert.ok(entries.some((e) => e.msgid === 'Long term split over lines'));
});

test('loadSourceTermKinds separates gettext plurals from context plurals', () => {
    assert.ok(kinds.gettextPlurals.has('Email sent to %d family.'));
    assert.ok(!kinds.gettextPlurals.has('Copied {{count}} members'));
    assert.deepEqual([...kinds.contextForms.get('Copied {{count}} members')].sort(), ['one', 'other']);
    assert.ok(!kinds.contextForms.has('Save'));
});

test('repairJoinedPlural re-slots a joined value for 2, 3, 4 and 6 form languages', () => {
    assert.deepEqual(repairJoinedPlural({ one: 'A|B', other: '' }), { one: 'A', other: 'B' });
    assert.deepEqual(
        repairJoinedPlural({ one: 'A|B|C', few: '', other: '' }),
        { one: 'A', few: 'B', other: 'C' },
    );
    assert.deepEqual(
        repairJoinedPlural({ one: 'A|B|C|D', few: '', many: '', other: '' }),
        { one: 'A', few: 'B', many: 'C', other: 'D' },
    );
    assert.deepEqual(
        repairJoinedPlural({ zero: 'Z|O|T|F|M|X', one: '', two: '', few: '', many: '', other: '' }),
        { zero: 'Z', one: 'O', two: 'T', few: 'F', many: 'M', other: 'X' },
    );
});

test('repairJoinedPlural blanks a joined value whose part count does not match', () => {
    assert.deepEqual(
        repairJoinedPlural({ one: 'A|B', few: '', many: '', other: '' }),
        { one: '', few: '', many: '', other: '' },
    );
});

test('repairJoinedPlural leaves normal values alone', () => {
    const complete = { one: 'A', other: 'B' };
    assert.deepEqual(repairJoinedPlural(complete), complete);
    assert.deepEqual(repairJoinedPlural({ one: '', other: '' }), { one: '', other: '' });
    assert.deepEqual(repairJoinedPlural({ other: 'A|B' }), { other: 'A|B' });
    assert.equal(repairJoinedPlural('Ctrl | Cmd'), 'Ctrl | Cmd');
});

test('buildPoeditorPayload never pipe-joins plurals', () => {
    const { payload, skipped } = buildPoeditorPayload(
        {
            Save: 'Speichern',
            'Email sent to %d family.': { one: 'E-Mail an %d Familie gesendet.', other: 'E-Mail an %d Familien gesendet.' },
            'Copied {{count}} members': { one: '{{count}} Mitglied kopiert', other: '{{count}} Mitglieder kopiert' },
        },
        kinds,
    );
    assert.deepEqual(skipped, []);
    assert.deepEqual(payload, {
        Save: 'Speichern',
        'Email sent to %d family.': { one: 'E-Mail an %d Familie gesendet.', other: 'E-Mail an %d Familien gesendet.' },
        one: { 'Copied {{count}} members': '{{count}} Mitglied kopiert' },
        other: { 'Copied {{count}} members': '{{count}} Mitglieder kopiert' },
    });
    assert.ok(!JSON.stringify(payload).includes('|'));
});

test('buildPoeditorPayload keeps every gettext form the language has', () => {
    const ru = { one: 'А', few: 'Б', many: 'В', other: 'Г' };
    const { payload } = buildPoeditorPayload({ 'Email sent to %d family.': ru }, kinds);
    assert.deepEqual(payload['Email sent to %d family.'], ru);
});

test('buildPoeditorPayload skips plurals it cannot place', () => {
    const { payload, skipped } = buildPoeditorPayload(
        {
            'Unknown {{count}} thing': { one: 'x', other: 'y' },
            'Copied {{count}} members': { one: 'a', few: 'b', other: 'c' },
        },
        kinds,
    );
    assert.deepEqual(skipped.sort(), ['Copied {{count}} members', 'Unknown {{count}} thing']);
    assert.deepEqual(payload, {});
});

test('every plural in the real messages.po is classified', () => {
    const messagesPo = fs.readFileSync(path.join(__dirname, '../../messages.po'), 'utf8');
    const real = loadSourceTermKinds(messagesPo);
    assert.ok(real.gettextPlurals.has('Two-factor authentication is required. You have %d day to enroll.'));
    assert.deepEqual([...real.contextForms.get('Copied {{count}} members')].sort(), ['one', 'other']);
});
