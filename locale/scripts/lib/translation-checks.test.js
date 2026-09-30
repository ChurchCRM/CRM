const { test } = require('node:test');
const assert = require('node:assert/strict');

const { validateTranslation, prefill, normalizeKey } = require('./translation-checks');

const STATEMENT = 'Dear %s Family,\n\nPlease find your %d giving statement from %s attached.';

test('keeps printf placeholders in source order', () => {
    assert.deepEqual(validateTranslation('fr', STATEMENT, 'Chère famille %s, votre relevé %d de %s.'), []);
});

test('rejects reordered placeholders without positional indexes', () => {
    const problems = validateTranslation('hi', STATEMENT, 'प्रिय %s परिवार, %s से आपका %d विवरण');
    assert.match(problems[0], /out of order/);
});

test('accepts reordered placeholders that use positional indexes', () => {
    assert.deepEqual(validateTranslation('hi', STATEMENT, 'प्रिय %1$s परिवार, %3$s से आपका %2$d विवरण'), []);
});

test('rejects a positional index whose type does not match the source', () => {
    const problems = validateTranslation('fr', STATEMENT, 'famille %1$s, %2$s, %3$s');
    assert.match(problems[0], /does not match the source/);
});

test('rejects mixed positional and plain placeholders', () => {
    const problems = validateTranslation('fr', STATEMENT, 'famille %1$s, %d, %s');
    assert.match(problems[0], /mixes positional/);
});

test('rejects a dropped placeholder', () => {
    assert.match(validateTranslation('fr', 'Sent %d emails', 'Envoyé')[0], /count differs/);
});

test('compares i18next placeholders by name', () => {
    assert.deepEqual(validateTranslation('fr', 'Copied {{count}} members', '{{count}} membres copiés'), []);
    assert.match(validateTranslation('fr', 'Copied {{count}} members', '{{nombre}} membres copiés')[0], /differ/);
});

test('rejects letters from a script that does not belong to the locale', () => {
    assert.match(validateTranslation('ta', 'Page Layout', 'பக்க লেআউட்')[0], /Bengali|unknown/);
    assert.match(validateTranslation('fr', 'Save', 'Сохранить')[0], /Cyrillic/);
});

test('accepts the locale script plus Latin brand names', () => {
    assert.deepEqual(validateTranslation('te', 'Open GitHub Issue', 'GitHub సమస్యను తెరవండి'), []);
    assert.deepEqual(validateTranslation('ja', 'Birthday', '誕生日のお知らせ'), []);
    assert.deepEqual(validateTranslation('zh-TW', 'Email', '電子郵件'), []);
});

test('skips the script check for a locale it does not know', () => {
    assert.deepEqual(validateTranslation('xx', 'Save', 'Сохранить'), []);
});

test('accepts a value identical to the key and rejects an empty one', () => {
    assert.deepEqual(validateTranslation('de', 'Offline', 'Offline'), []);
    assert.deepEqual(validateTranslation('de', 'Offline', ''), ['empty']);
});

test('checks plural objects slot by slot and lets "one" drop %d', () => {
    assert.deepEqual(validateTranslation('fr', 'Sent %d email', { one: 'Un e-mail envoyé', other: '%d e-mails envoyés' }), []);
    assert.deepEqual(validateTranslation('fr', 'Sent %d email', { one: '', other: '%d e-mails envoyés' }), ['plural form "one" is empty']);
});

test('normalizeKey ignores case and trailing punctuation', () => {
    assert.equal(normalizeKey('Missing People:'), normalizeKey('missing people'));
});

test('prefill fills allowlisted terms with themselves', () => {
    const result = prefill({ Offline: '', Save: '' }, {}, new Set(['Offline']));
    assert.deepEqual(result.filled, { Offline: 'Offline' });
    assert.deepEqual(result.fromAllowlist, ['Offline']);
});

test('prefill reuses a translation that differs only in inner case or trailing punctuation', () => {
    const translated = { 'Membership anniversaries': 'Maadhimisho ya uanachama', 'Send email.': 'Tuma barua pepe' };
    const result = prefill({ 'Membership Anniversaries': '', 'Send email': '' }, translated);
    assert.deepEqual(result.filled, { 'Membership Anniversaries': 'Maadhimisho ya uanachama', 'Send email': 'Tuma barua pepe' });
});

test('prefill leaves a different first-letter case to the model', () => {
    const result = prefill({ 'system logs': '' }, { 'System Logs': 'Systémové protokoly' });
    assert.deepEqual(result.filled, {});
});

test('prefill skips ambiguous matches and placeholder mismatches', () => {
    const ambiguous = prefill({ 'Open Link': '' }, { 'Open link': 'Ouvrir', 'Open link.': 'Ouvre' });
    assert.deepEqual(ambiguous.filled, {});
    const mismatch = prefill({ 'Sent %d emails': '' }, { 'sent emails': 'Envoyé' });
    assert.deepEqual(mismatch.filled, {});
});

test('prefill leaves plural entries and already translated terms alone', () => {
    const result = prefill({ Family: { one: '', other: '' }, Done: 'Fait' }, {});
    assert.deepEqual(result.filled, {});
});
