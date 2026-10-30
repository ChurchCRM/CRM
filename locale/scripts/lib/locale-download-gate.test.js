const { test } = require('node:test');
const assert = require('node:assert/strict');

const { localeDownloadGate, parsePorcelain } = require('./locale-download-gate');

const read = (files) => (file) => {
    if (!Object.hasOwn(files, file)) {
        throw new Error('missing');
    }
    return files[file];
};

test('merges a download that stays inside the locale trees', () => {
    const result = localeDownloadGate(
        [
            { path: 'src/locale/i18n/fr_FR.json' },
            { path: 'src/locale/textdomain/fr_FR/LC_MESSAGES/messages.po' },
            { path: 'src/locale/textdomain/fr_FR/LC_MESSAGES/messages.mo' },
            { path: 'locale/terms/missing/fr/fr-1.json' },
            { path: 'locale/terms/missing/de/de-1.json', deleted: true },
            { path: 'src/locale/i18n/en_US.json' },
        ],
        read({
            'src/locale/i18n/fr_FR.json': '{ "Save": "Enregistrer" }\n',
            'locale/terms/missing/fr/fr-1.json': '{}\n',
            'src/locale/i18n/en_US.json': '',
        }),
    );
    assert.equal(result.merge, true);
    assert.deepEqual(result.reasons, []);
});

test('leaves the PR open when JSON does not parse', () => {
    const result = localeDownloadGate(
        [{ path: 'src/locale/i18n/fr_FR.json' }],
        read({ 'src/locale/i18n/fr_FR.json': '{ "Save": ' }),
    );
    assert.equal(result.merge, false);
    assert.deepEqual(result.reasons, ['invalid json: src/locale/i18n/fr_FR.json']);
});

test('leaves the PR open for an empty translation file', () => {
    const result = localeDownloadGate(
        [{ path: 'src/locale/i18n/fr_FR.json' }],
        read({ 'src/locale/i18n/fr_FR.json': '   \n' }),
    );
    assert.equal(result.merge, false);
    assert.deepEqual(result.reasons, ['invalid json: src/locale/i18n/fr_FR.json']);
});

test('leaves the PR open when a path is outside the download trees', () => {
    const result = localeDownloadGate(
        [
            { path: 'src/locale/i18n/fr_FR.json' },
            { path: 'src/ChurchCRM/Service/PersonService.php' },
        ],
        read({ 'src/locale/i18n/fr_FR.json': '{}' }),
    );
    assert.equal(result.merge, false);
    assert.deepEqual(result.reasons, ['unexpected path: src/ChurchCRM/Service/PersonService.php']);
});

test('does not merge when the download changed nothing', () => {
    const result = localeDownloadGate([], () => '{}');
    assert.equal(result.merge, false);
    assert.deepEqual(result.reasons, ['no locale changes']);
});

test('reads porcelain status, including a rename', () => {
    const files = parsePorcelain(
        [
            ' M src/locale/i18n/fr_FR.json',
            ' D locale/terms/missing/de/de-1.json',
            '?? locale/terms/missing/fr/fr-2.json',
            'R  src/locale/i18n/old.json -> src/locale/i18n/new.json',
            '',
        ].join('\n'),
    );
    assert.deepEqual(files, [
        { path: 'src/locale/i18n/fr_FR.json', deleted: false },
        { path: 'locale/terms/missing/de/de-1.json', deleted: true },
        { path: 'locale/terms/missing/fr/fr-2.json', deleted: false },
        { path: 'src/locale/i18n/old.json', deleted: true },
        { path: 'src/locale/i18n/new.json', deleted: false },
    ]);
});
