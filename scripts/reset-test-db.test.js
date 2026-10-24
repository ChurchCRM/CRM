const test = require('node:test');
const assert = require('node:assert');
const { STEPS } = require('./reset-test-db');

test('the schema loads before the test data', () => {
    const files = STEPS.map((step) => step.file).filter(Boolean);
    assert.deepStrictEqual(files, ['src/mysql/install/Install.sql', 'cypress/data/seed.sql']);
});

test('the database is dropped and recreated first', () => {
    assert.match(STEPS[0].script, /DROP DATABASE IF EXISTS .*CREATE DATABASE/);
    assert.strictEqual(STEPS[0].file, undefined);
});
