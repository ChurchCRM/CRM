const assert = require('node:assert/strict');
const { test } = require('node:test');
const { findViolations } = require('../../scripts/lint/validate-mysql-index-length.js');

const table = (column, key) => `CREATE TABLE IF NOT EXISTS \`t\` (
  \`id\` int NOT NULL,
  \`email\` ${column} NOT NULL,
  PRIMARY KEY (\`id\`),
  ${key}
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;`;

test('flags a utf8mb4 varchar(255) index', () => {
    const found = findViolations(table('varchar(255)', 'KEY `e` (`email`, `id`)'), 'x.sql');
    assert.equal(found.length, 1);
    assert.match(found[0], /1020 bytes/);
});

test('accepts a prefix index and a 190-character column', () => {
    assert.deepEqual(findViolations(table('varchar(255)', 'KEY `e` (`email`(190), `id`)'), 'x.sql'), []);
    assert.deepEqual(findViolations(table('varchar(190)', 'UNIQUE KEY `e` (`email`)'), 'x.sql'), []);
});

test('ignores unindexed wide columns', () => {
    assert.deepEqual(findViolations(table('varchar(255)', 'KEY `i` (`id`)'), 'x.sql'), []);
});
