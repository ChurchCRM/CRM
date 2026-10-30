const test = require('node:test');
const assert = require('node:assert/strict');
const { findViolations } = require('./validate-custom-field-access');

test('flags a page that maps the FieldSecurity option itself', () => {
    const source = 'if ($user->isEnabledSecurity($aSecurityType[$custom_FieldSec])) {';
    assert.equal(findViolations('src/SomePage.php', source).length, 1);
});

test('flags hand-written writes to the custom value tables', () => {
    for (const sql of [
        "RunQuery('REPLACE INTO person_custom SET c1 = 1');",
        "RunQuery('UPDATE family_custom SET c2 = NULL');",
        'INSERT IGNORE INTO `person_custom` (per_ID) VALUES (1)',
    ]) {
        assert.equal(findViolations('src/SomePage.php', sql).length, 1, sql);
    }
});

test('allows reads and unrelated permission checks', () => {
    assert.deepEqual(findViolations('src/SomePage.php', "RunPreparedQuery('SELECT * FROM person_custom WHERE per_ID = ?')"), []);
    assert.deepEqual(findViolations('src/SomePage.php', "$user->isEnabledSecurity('bFinance')"), []);
    assert.deepEqual(findViolations('src/SomePage.php', '$service->canView($field->getFieldSecurity())'), []);
});

test('does not flag the service or the field definition screens', () => {
    const source = "$user->isEnabledSecurity($names[$fieldSecurityOptionId]) UPDATE person_custom SET c1 = 1";
    assert.deepEqual(findViolations('src/ChurchCRM/Service/CustomFieldService.php', source), []);
    assert.deepEqual(findViolations('src/PersonCustomFieldsEditor.php', source), []);
});
