const test = require('node:test');
const assert = require('node:assert');
const { compare, shippedLocaleCodes, upgradeLocaleCodes } = require('./validate-upgrade-locales');

const sqlWith = (codes) =>
    [
        "JOIN `user_settings` s ON s.`user_id` = u.`usr_per_ID` AND s.`setting_name` = 'ui.locale'",
        'WHERE u.`usr_EditSelf` = 1',
        `  AND CAST(TRIM(s.\`setting_value\`) AS BINARY) IN (${codes.map((code) => `'${code}'`).join(', ')})`,
        "ON DUPLICATE KEY UPDATE `psi_Language` = COALESCE(`psi_Language`, TRIM(s.`setting_value`));",
    ].join('\n');

const shipped = shippedLocaleCodes({ English: { locale: 'en_US' }, German: { locale: 'de_DE' }, Swahili: { locale: 'sw' } });

test('a list equal to locales.json passes, in any order', () => {
    assert.deepStrictEqual(compare(upgradeLocaleCodes(sqlWith(['sw', 'en_US', 'de_DE'])), shipped), []);
});

test('a locale added to locales.json but not the SQL fails', () => {
    const found = compare(upgradeLocaleCodes(sqlWith(['en_US', 'de_DE'])), shipped);
    assert.strictEqual(found.length, 1);
    assert.match(found[0], /'sw' is in src\/locale\/locales.json but missing/);
});

test('a code the SQL keeps after locales.json dropped it fails, and so does a repeat', () => {
    const found = compare(upgradeLocaleCodes(sqlWith(['en_US', 'de_DE', 'sw', 'fr_FR', 'de_DE'])), shipped);
    assert.strictEqual(found.length, 2);
    assert.match(found.join('\n'), /'de_DE' is listed twice/);
    assert.match(found.join('\n'), /'fr_FR' is in the language list but not/);
});

test('an upgrade without the language list fails', () => {
    assert.strictEqual(compare(upgradeLocaleCodes('SELECT 1;'), shipped).length, 1);
});
