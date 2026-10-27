#!/usr/bin/env node

/**
 * supported-databases.json is the one place the supported MySQL and MariaDB
 * releases are written. This script rewrites the files that cannot read it
 * (compose images, the nightly matrix, the setup page text).
 *   node scripts/sync-supported-databases.js          rewrite them
 *   node scripts/sync-supported-databases.js --check  fail when one is out of date
 */

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const check = process.argv.includes('--check');
const versions = JSON.parse(fs.readFileSync(path.join(root, 'supported-databases.json'), 'utf8'));

for (const [name, version] of Object.entries(versions)) {
    if (!/^\d+(\.\d+)*$/.test(version)) {
        console.error(`supported-databases.json: "${name}" must be a version like 8.4, found "${version}"`);
        process.exit(1);
    }
}

// Each pattern captures (prefix)(version)(suffix); only the version is replaced.
const targets = {
    '.github/workflows/build-test-nightly.yml': [
        [/(MySQL )([\d.]+)( LTS)/g, 'mysql'],
        [/(MariaDB )([\d.]+)( LTS)/g, 'mariadb'],
        [/(db_image: \["mysql:)([\d.]+)(")/g, 'mysql'],
    ],
    'docker/README.md': [[/(`mariadb:)([\d.]+)(`)/g, 'mariadb']],
    'docker/docker-compose.yaml': [[/(image: mariadb:)([\d.]+)()/g, 'mariadb']],
    'docker/docker-compose.dev.yaml': [[/(image: mariadb:)([\d.]+)()/g, 'mariadb']],
    'docker/docker-compose.parallel.yaml': [[/(image: mariadb:)([\d.]+)()/g, 'mariadb']],
    'docker/docker-compose.mysql.yaml': [[/(mysql:)(\d[\d.]*)()/g, 'mysql']],
    'src/setup/templates/setup-steps.php': [
        [/(supports MySQL )([\d.]+)(\+)/g, 'mysql'],
        [/(and MariaDB )([\d.]+)(\+ \(the LTS)/g, 'mariadb'],
    ],
};

const stale = [];
for (const [file, patterns] of Object.entries(targets)) {
    const full = path.join(root, file);
    const before = fs.readFileSync(full, 'utf8');
    let after = before;
    for (const [pattern, database] of patterns) {
        if (!pattern.test(after)) {
            console.error(`${file}: no ${database} version found for ${pattern}. Update scripts/sync-supported-databases.js.`);
            process.exit(1);
        }
        pattern.lastIndex = 0;
        after = after.replace(pattern, (match, prefix, version, suffix) => prefix + versions[database] + suffix);
    }
    if (after !== before) {
        stale.push(file);
        if (!check) {
            fs.writeFileSync(full, after);
        }
    }
}

if (check && stale.length) {
    console.error(`Out of date with supported-databases.json:\n${stale.map((file) => `  ${file}`).join('\n')}`);
    console.error('Run: node scripts/sync-supported-databases.js');
    process.exit(1);
}
if (!check && stale.length) {
    console.log(`Updated:\n${stale.map((file) => `  ${file}`).join('\n')}`);
}
