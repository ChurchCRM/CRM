#!/usr/bin/env node

/**
 * Verifies every hand-maintained Propel model subclass in
 * src/ChurchCRM/model/ChurchCRM/ actually has a generated Base class to
 * extend.
 *
 * Propel generates one "Base/<Name>.php" per table into src/ChurchCRM/model/
 * ChurchCRM/Base/ (gitignored, rebuilt by `npm run build:orm` / a fresh
 * `composer install`) — the hand-maintained skeleton subclass just extends
 * it. If a subclass is added without its Propel schema entry (or the schema
 * changed and Base/ was never rebuilt), the subclass silently references a
 * class that will never exist, fataling the moment anything actually
 * instantiates it — see PR #9666, which added PledgeDenomination(Query).php
 * extending a Base class that was never generated or committed anywhere.
 *
 * This can only meaningfully run once Base/ has actually been generated
 * (i.e. after `composer install` / `npm run build:orm`) — it's a build/CI
 * check, not a bare pre-commit one, and skips gracefully (not a failure) if
 * Base/ doesn't exist yet in this checkout.
 */

const fs = require('fs');
const path = require('path');

const MODEL_ROOT = path.join(__dirname, '..', 'src', 'ChurchCRM', 'model', 'ChurchCRM');
const BASE_ROOT = path.join(MODEL_ROOT, 'Base');

console.log('🔍 Propel Base Class Validation');
console.log('================================\n');

if (!fs.existsSync(BASE_ROOT)) {
    console.log('ℹ️  src/ChurchCRM/model/ChurchCRM/Base/ not built yet (run `composer install` first) — skipping.');
    process.exit(0);
}

const subclassFiles = fs
    .readdirSync(MODEL_ROOT, { withFileTypes: true })
    .filter((entry) => entry.isFile() && entry.name.endsWith('.php'))
    .map((entry) => path.join(MODEL_ROOT, entry.name));

console.log(`📋 Checking ${subclassFiles.length} model subclass file(s) under src/ChurchCRM/model/ChurchCRM/\n`);

const missing = [];

for (const filePath of subclassFiles) {
    const content = fs.readFileSync(filePath, 'utf8');
    // Matches: use ChurchCRM\model\ChurchCRM\Base\<Name> as Base<Name>;
    const match = content.match(/use\s+ChurchCRM\\model\\ChurchCRM\\Base\\(\w+)\s+as\s+\w+;/);
    if (!match) {
        continue; // not a generated-base skeleton subclass — not this check's concern
    }

    const baseClassName = match[1];
    const expectedBasePath = path.join(BASE_ROOT, `${baseClassName}.php`);
    if (!fs.existsSync(expectedBasePath)) {
        missing.push({
            filePath: path.relative(process.cwd(), filePath),
            baseClassName,
            expectedBasePath: path.relative(process.cwd(), expectedBasePath),
        });
    }
}

if (missing.length > 0) {
    console.error(`❌ Found ${missing.length} subclass(es) referencing a Base class that was never generated:\n`);
    for (const { filePath, baseClassName, expectedBasePath } of missing) {
        console.error(`  ${filePath}`);
        console.error(`    extends Base\\${baseClassName}, but ${expectedBasePath} does not exist\n`);
    }
    console.error(
        'Fix: add the missing table to the Propel schema and rebuild (npm run build:orm), or remove the subclass if it was added by mistake.'
    );
    process.exit(1);
}

console.log('✨ Every model subclass has a matching generated Base class!');
