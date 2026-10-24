const test = require('node:test');
const assert = require('node:assert');
const { problems } = require('./validate-seed-data-only');

test('data-only seed passes', () => {
    const sql = [
        'DELETE FROM `config_cfg`;',
        'CREATE TABLE `groupprop_2` (`per_ID` int);',
        'INSERT INTO `person_per` (`per_ID`, `per_FirstName`) VALUES (1,\'A\');',
    ].join('\n');
    assert.deepStrictEqual(problems(sql), []);
});

test('a CREATE TABLE or DROP TABLE for an installed table fails', () => {
    assert.strictEqual(problems('DROP TABLE IF EXISTS `note_nte`;\nCREATE TABLE `note_nte` (`nte_ID` int);').length, 2);
});

test('a positional insert fails', () => {
    const found = problems("INSERT INTO `person_per` VALUES (1,'A');");
    assert.strictEqual(found.length, 1);
    assert.match(found[0], /names no columns/);
});

test('indented and unquoted statements are caught', () => {
    const sql = ['  CREATE TABLE note_nte (nte_ID int);', "\tINSERT INTO person_per VALUES (1,'A');"].join('\n');
    assert.strictEqual(problems(sql).length, 2);
});

test('tabs and newlines between keywords are caught', () => {
    const sql = ['CREATE\tTABLE `note_nte` (a int);', "INSERT\nINTO `person_per` VALUES (1);"].join('\n');
    assert.strictEqual(problems(sql).length, 2);
});
