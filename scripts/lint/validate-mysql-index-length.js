#!/usr/bin/env node

/**
 * Fails when an index in Install.sql or an upgrade script covers a character
 * column wider than 767 bytes.
 *
 * MariaDB 10.0 and MySQL 5.6 reject such an index with SQLSTATE 1071 unless
 * large prefixes are enabled, which shared hosts rarely allow. A utf8mb4
 * column wider than 191 characters (utf8: 255) needs an index prefix:
 * KEY `x` (`col`(190)).
 */

const fs = require('node:fs');
const path = require('node:path');

const REPO_ROOT = path.join(__dirname, '..', '..');
const MYSQL_DIR = path.join(REPO_ROOT, 'src', 'mysql');
const MAX_COLUMN_BYTES = 767;

function bytesPerChar(tableTail) {
    if (/CHARSET\s*=\s*utf8mb4/i.test(tableTail)) {
        return 4;
    }
    if (/CHARSET\s*=\s*utf8(mb3)?\b/i.test(tableTail)) {
        return 3;
    }
    return 1;
}

function splitDefinitions(body) {
    const parts = [];
    let depth = 0;
    let current = '';
    for (const char of body) {
        if (char === '(') {
            depth++;
        } else if (char === ')') {
            depth--;
        }
        if (char === ',' && depth === 0) {
            parts.push(current);
            current = '';
        } else {
            current += char;
        }
    }
    parts.push(current);
    return parts.map((part) => part.trim()).filter(Boolean);
}

function findViolations(sql, label) {
    const violations = [];
    const stripped = sql.replace(/--[^\n]*/g, '');
    const tablePattern = /CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?\s*\(/gi;
    let match = tablePattern.exec(stripped);

    while (match !== null) {
        const start = match.index + match[0].length;
        let depth = 1;
        let end = start;
        while (end < stripped.length && depth > 0) {
            if (stripped[end] === '(') {
                depth++;
            } else if (stripped[end] === ')') {
                depth--;
            }
            end++;
        }
        const body = stripped.slice(start, end - 1);
        const tail = stripped.slice(end, stripped.indexOf(';', end));
        const perChar = bytesPerChar(tail);
        const columns = {};

        for (const definition of splitDefinitions(body)) {
            const column = definition.match(/^`(\w+)`\s+(?:var)?char\((\d+)\)/i);
            if (column) {
                columns[column[1]] = Number(column[2]);
            }
        }

        for (const definition of splitDefinitions(body)) {
            const index = definition.match(/^(?:UNIQUE\s+|FULLTEXT\s+)?(?:KEY|INDEX)\s+`?(\w+)`?\s*\((.+)\)$/is);
            if (!index) {
                continue;
            }
            for (const part of splitDefinitions(index[2])) {
                const indexed = part.match(/^`(\w+)`(?:\s*\((\d+)\))?/);
                if (!indexed || columns[indexed[1]] === undefined) {
                    continue;
                }
                const chars = indexed[2] ? Number(indexed[2]) : columns[indexed[1]];
                if (chars * perChar > MAX_COLUMN_BYTES) {
                    violations.push(
                        `${label}: ${match[1]}.${index[1]} indexes ${indexed[1]} as ${chars * perChar} bytes (limit ${MAX_COLUMN_BYTES}); use a prefix such as \`${indexed[1]}\`(${Math.floor(MAX_COLUMN_BYTES / perChar)})`,
                    );
                }
            }
        }
        match = tablePattern.exec(stripped);
    }
    return violations;
}

function sqlFiles() {
    const upgradeDir = path.join(MYSQL_DIR, 'upgrade');
    return [
        path.join(MYSQL_DIR, 'install', 'Install.sql'),
        ...fs
            .readdirSync(upgradeDir)
            .filter((name) => name.endsWith('.sql'))
            .map((name) => path.join(upgradeDir, name)),
    ];
}

function main() {
    const violations = sqlFiles().flatMap((file) =>
        findViolations(fs.readFileSync(file, 'utf8'), path.relative(REPO_ROOT, file)),
    );
    if (violations.length > 0) {
        console.error(violations.join('\n'));
        process.exit(1);
    }
    console.log('No index covers a character column wider than 767 bytes.');
}

if (require.main === module) {
    main();
}

module.exports = { findViolations };
