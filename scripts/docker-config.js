#!/usr/bin/env node

/**
 * Prepares src/Include/Config.php for a docker profile.
 *   node scripts/docker-config.js docker/Config.php   copy this file into place
 *   node scripts/docker-config.js --remove            delete it (new-system setup wizard)
 *
 * A Config.php that does not carry the docker development password is a real
 * install's configuration. It is copied to Config.php.<timestamp>.bak first.
 */

const fs = require('fs');
const path = require('path');

const target = path.join(__dirname, '..', 'src', 'Include', 'Config.php');
const arg = process.argv[2];

function backUpRealConfig() {
    if (!fs.existsSync(target) || fs.readFileSync(target, 'utf8').includes("$sPASSWORD = 'changeme'")) {
        return;
    }
    const stamp = new Date().toISOString().replace(/\D/g, '').slice(0, 14);
    const backup = `${target}.${stamp}.bak`;
    fs.copyFileSync(target, backup, fs.constants.COPYFILE_EXCL);
    fs.chmodSync(backup, 0o600);
    console.warn(`⚠ ${path.relative(process.cwd(), target)} is not a docker development config. Backed up to ${path.relative(process.cwd(), backup)}`);
}

if (arg === '--remove') {
    backUpRealConfig();
    fs.rmSync(target, { force: true });
} else if (arg) {
    backUpRealConfig();
    fs.copyFileSync(path.resolve(arg), target);
} else {
    console.error('usage: docker-config.js <source-file> | --remove');
    process.exit(1);
}
