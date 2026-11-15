#!/usr/bin/env node

/**
 * The upgrade wizard of the installed (older) release is still running when
 * the new files land, and it calls into the new code. These methods must keep
 * existing, or the wizard ends in a 500 after the files are already replaced.
 * Keep each one until the oldest release that calls it is out of support.
 */

const fs = require('node:fs');
const path = require('node:path');

const SRC = path.join(__dirname, '..', '..', 'src', 'ChurchCRM');

const HANDOFF = [
    ['Service/AppIntegrityService.php', 'clearIntegrityCache'],
    ['Service/AppIntegrityService.php', 'deleteOrphanedFiles'],
    ['Service/UpgradeService.php', 'upgradeDatabaseVersion'],
    ['Utils/FileSystemUtils.php', 'moveDir'],
];

const missing = HANDOFF.filter(([file, method]) => {
    const source = fs.readFileSync(path.join(SRC, file), 'utf8');
    return !new RegExp(`function\\s+${method}\\s*\\(`).test(source);
});

if (missing.length > 0) {
    console.error(
        missing.map(([file, method]) => `${file}: ${method}() is called by older upgrade wizards and must exist`).join('\n'),
    );
    process.exit(1);
}
console.log('Upgrade handoff methods present.');
