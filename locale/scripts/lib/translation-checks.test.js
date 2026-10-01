const { test } = require('node:test');
const assert = require('node:assert/strict');

const fs = require('node:fs');
const path = require('node:path');

const { validateTranslation, prefill, normalizeKey, scriptTest } = require('./translation-checks');

const LATIN = ['Latin'];

const STATEMENT = 'Dear %s Family,\n\nPlease find your %d giving statement from %s attached.';

test('keeps printf placeholders in source order', () => {
    assert.deepEqual(validateTranslation(LATIN, STATEMENT, 'Chère famille %s, votre relevé %d de %s.'), []);
});

test('rejects reordered placeholders without positional indexes', () => {
    const problems = validateTranslation(['Devanagari'], STATEMENT, 'प्रिय %s परिवार, %s से आपका %d विवरण');
    assert.match(problems[0], /out of order/);
});

test('accepts reordered placeholders that use positional indexes', () => {
    assert.deepEqual(validateTranslation(['Devanagari'], STATEMENT, 'प्रिय %1$s परिवार, %3$s से आपका %2$d विवरण'), []);
});

test('rejects a positional index whose type does not match the source', () => {
    const problems = validateTranslation(LATIN, STATEMENT, 'famille %1$s, %2$s, %3$s');
    assert.match(problems[0], /does not match the source/);
});

test('rejects mixed positional and plain placeholders', () => {
    const problems = validateTranslation(LATIN, STATEMENT, 'famille %1$s, %d, %s');
    assert.match(problems[0], /mixes positional/);
});

test('rejects a dropped placeholder', () => {
    assert.match(validateTranslation(LATIN, 'Sent %d emails', 'Envoyé')[0], /count differs/);
});

test('compares i18next placeholders by name', () => {
    assert.deepEqual(validateTranslation(LATIN, 'Copied {{count}} members', '{{count}} membres copiés'), []);
    assert.match(validateTranslation(LATIN, 'Copied {{count}} members', '{{nombre}} membres copiés')[0], /differ/);
});

test('rejects letters from a script that does not belong to the locale', () => {
    assert.match(validateTranslation(['Tamil'], 'Page Layout', 'பக்க লেআউட்')[0], /outside this locale's scripts \(Tamil\)/);
    assert.match(validateTranslation(LATIN, 'Save', 'Сохранить')[0], /outside this locale's scripts \(Latin\)/);
});

test('accepts the locale script plus Latin brand names', () => {
    assert.deepEqual(validateTranslation(['Telugu'], 'Open GitHub Issue', 'GitHub సమస్యను తెరవండి'), []);
    assert.deepEqual(validateTranslation(['Han', 'Hiragana', 'Katakana'], 'Birthday', '誕生日のお知らせ'), []);
    assert.deepEqual(validateTranslation(['Han'], 'Email', '電子郵件'), []);
});

test('skips the script check for a locale without scripts', () => {
    assert.deepEqual(validateTranslation(undefined, 'Save', 'Сохранить'), []);
});

test('accepts a value identical to the key and rejects an empty one', () => {
    assert.deepEqual(validateTranslation(LATIN, 'Offline', 'Offline'), []);
    assert.deepEqual(validateTranslation(LATIN, 'Offline', ''), ['empty']);
});

test('checks plural objects slot by slot and lets "one" drop %d', () => {
    assert.deepEqual(validateTranslation(LATIN, 'Sent %d email', { one: 'Un e-mail envoyé', other: '%d e-mails envoyés' }), []);
    assert.deepEqual(validateTranslation(LATIN, 'Sent %d email', { one: '', other: '%d e-mails envoyés' }), ['plural form "one" is empty']);
});

test('plural forms that carry the number must keep the placeholders', () => {
    const problems = validateTranslation(LATIN, '%d Members', { one: 'Un membre', other: 'Membres' });
    assert.match(problems[0], /form "other" printf placeholder count differs/);
    assert.deepEqual(validateTranslation(LATIN, '%d Members', { one: 'Un membre', other: '%d membres' }), []);
    assert.deepEqual(validateTranslation(LATIN, 'Copied {{count}} members', { one: 'Un membre copié', other: '{{count}} membres copiés' }), []);
    assert.match(validateTranslation(LATIN, 'Copied {{count}} members', { one: 'Un membre copié', other: 'Membres copiés' })[0], /form "other"/);
});

test('scriptTest accepts script names and rejects anything that could alter the pattern', () => {
    assert.ok(scriptTest('Katakana'));
    assert.ok(scriptTest('Old_Italic'));
    for (const bad of ['', 'Han}|.*', 'Han}.{', 'Hangull', '.*', 'Latin\\', null, 42]) {
        assert.equal(scriptTest(bad), null, `${String(bad)} must be rejected`);
    }
});

test('accepts marks shared between scripts', () => {
    assert.deepEqual(validateTranslation(['Han', 'Hiragana', 'Katakana'], 'Server', 'サーバー'), []);
    assert.deepEqual(validateTranslation(['Han', 'Hiragana', 'Katakana'], 'Email', 'メール'), []);
    assert.deepEqual(validateTranslation(['Arabic'], 'Email', 'بريــد'), []);
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

test('every locale in locales.json declares valid scripts that cover its own name', () => {
    const locales = JSON.parse(fs.readFileSync(path.join(__dirname, '../../../src/locale/locales.json'), 'utf8'));
    for (const [name, entry] of Object.entries(locales)) {
        assert.ok(Array.isArray(entry.scripts) && entry.scripts.length > 0, `${name}: add "scripts" (e.g. ["Latin"]) to locales.json`);
        for (const script of entry.scripts) {
            assert.ok(scriptTest(script), `${name}: "${script}" is not a Unicode script name`);
        }
        if (entry.nativeName) {
            assert.deepEqual(validateTranslation(entry.scripts, entry.nativeName, `${entry.nativeName}.`), [], `${name}: nativeName is outside its scripts`);
        }
    }
});
