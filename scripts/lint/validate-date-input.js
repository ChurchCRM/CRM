#!/usr/bin/env node

/**
 * Dates a person types or submits are read and shown through ChurchCRM\Utils\DateTimeUtils.
 *
 * Inside the app a date is ISO (Y-m-d). Only a date-picker text field uses the system
 * sDatePickerFormat, and DateTimeUtils converts at that edge: normalizeDate() / parseAndValidate()
 * in, formatForDatePicker() out. A page that runs strtotime() or createFromFormat() on request
 * input, or prints an ISO date into a picker field, breaks for every church whose format is not Y-m-d.
 */

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..', '..');
const srcRoot = path.join(root, 'src');

const ALLOWED = new Set(['src/ChurchCRM/Utils/DateTimeUtils.php']);
const SKIPPED_DIRS = new Set(['vendor', 'node_modules', 'generated-conf', 'locale', 'skin']);
const SKIPPED_PREFIXES = ['src/ChurchCRM/model/ChurchCRM/Base/', 'src/ChurchCRM/model/ChurchCRM/Map/'];

const REQUEST_INPUT = /\$_(POST|GET|REQUEST)\b|getParsedBody\(|getQueryParams\(/;

const RULES = [
    {
        test: (line) => /\b(strtotime|createFromFormat)\s*\(/.test(line) && REQUEST_INPUT.test(line),
        message: 'parses request input as a date itself; use DateTimeUtils::normalizeDate() or parseAndValidate()',
    },
    {
        test: (line) => /InputUtils::filterDate\s*\(/.test(line),
        message: 'InputUtils::filterDate() is gone; use DateTimeUtils::normalizeDate()',
    },
    {
        test: (line) => /date-picker/.test(line) && /value="<\?=[^"]*\bdate\(/.test(line) && !/formatForDatePicker/.test(line),
        message: 'prints an ISO date into a date-picker field; wrap it in DateTimeUtils::formatForDatePicker()',
    },
];

const REQUEST_VARIABLE = /^\s*(\$\w+)\s*=[^=].*(?:\$_(?:POST|GET|REQUEST)\b|getParsedBody\(|getQueryParams\(|\$(?:input|body|post|query|params)\[)/;
const PARSES_DATE = /\b(strtotime|createFromFormat)\s*\(([^;]*)/;

function findViolations(relativePath, source) {
    if (ALLOWED.has(relativePath) || SKIPPED_PREFIXES.some((prefix) => relativePath.startsWith(prefix))) {
        return [];
    }
    const violations = [];
    const requestVariables = new Set();
    source.split('\n').forEach((line, index) => {
        const assigned = REQUEST_VARIABLE.exec(line);
        if (assigned && /date|start|end|from|to|day|sunday/i.test(assigned[1])) {
            requestVariables.add(assigned[1]);
        }
        const parsed = PARSES_DATE.exec(line);
        if (parsed && !parsed[2].includes('DateTimeUtils::') && [...requestVariables].some((name) => parsed[2].includes(name))) {
            violations.push({ file: relativePath, line: index + 1, message: 'parses a request date held in a variable; use DateTimeUtils::normalizeDate() or parseIsoDate()' });
        }
        for (const rule of RULES) {
            if (rule.test(line)) {
                violations.push({ file: relativePath, line: index + 1, message: rule.message });
            }
        }
    });
    return violations;
}

function phpFiles(dir) {
    const files = [];
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        if (entry.isDirectory()) {
            if (!SKIPPED_DIRS.has(entry.name)) {
                files.push(...phpFiles(path.join(dir, entry.name)));
            }
        } else if (entry.name.endsWith('.php')) {
            files.push(path.join(dir, entry.name));
        }
    }
    return files;
}

function main() {
    const violations = phpFiles(srcRoot).flatMap((file) => {
        const relativePath = path.relative(root, file).split(path.sep).join('/');
        return findViolations(relativePath, fs.readFileSync(file, 'utf8'));
    });

    if (violations.length === 0) {
        console.log('Date input: every typed date is read and shown through DateTimeUtils.');
        return;
    }
    for (const violation of violations) {
        console.error(`${violation.file}:${violation.line} ${violation.message}`);
    }
    process.exit(1);
}

if (require.main === module) {
    main();
}

module.exports = { findViolations };
