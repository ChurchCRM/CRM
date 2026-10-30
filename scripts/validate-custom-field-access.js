#!/usr/bin/env node

/**
 * Custom field access goes through ChurchCRM\Service\CustomFieldService.
 *
 * The service is the one place that maps a field's FieldSecurity option id to a permission and
 * the one place that writes `person_custom` / `family_custom` values. A page that maps the id
 * itself, or writes those tables with its own SQL, can show or change a field the signed-in
 * user may not touch.
 */

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const srcRoot = path.join(root, 'src');

// The service, the screens that define fields (they own the FieldSecurity setting and the
// column DDL) and the administrator CSV import, which writes with its own parameterized upsert.
const ALLOWED = new Set([
    'src/ChurchCRM/Service/CustomFieldService.php',
    'src/PersonCustomFieldsEditor.php',
    'src/FamilyCustomFieldsEditor.php',
    'src/PersonCustomFieldsRowOps.php',
    'src/FamilyCustomFieldsRowOps.php',
    'src/admin/routes/api/import.php',
]);

const SKIPPED_DIRS = new Set(['vendor', 'node_modules', 'generated-conf', 'locale', 'skin']);
const SKIPPED_PREFIXES = ['src/ChurchCRM/model/ChurchCRM/Base/', 'src/ChurchCRM/model/ChurchCRM/Map/'];

const RULES = [
    {
        pattern: /isEnabledSecurity\s*\([^)]*(FieldSec|FieldSecurity|aSecurityType)/,
        message: 'maps a custom field security option itself; use CustomFieldService::canView()',
    },
    {
        pattern: /\b(REPLACE\s+INTO|INSERT\s+(IGNORE\s+)?INTO|UPDATE)\s+`?(person_custom|family_custom)\b/i,
        message: 'writes custom field values with its own SQL; use CustomFieldService',
    },
];

function findViolations(relativePath, source) {
    if (ALLOWED.has(relativePath) || SKIPPED_PREFIXES.some((prefix) => relativePath.startsWith(prefix))) {
        return [];
    }
    const violations = [];
    source.split('\n').forEach((line, index) => {
        for (const rule of RULES) {
            if (rule.pattern.test(line)) {
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
        console.log('Custom field access: every check and write goes through CustomFieldService.');
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
