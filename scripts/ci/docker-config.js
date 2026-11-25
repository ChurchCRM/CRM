#!/usr/bin/env node

/**
 * Prepares src/Include/Config.php for a docker profile.
 *   node scripts/ci/docker-config.js docker/Config.php   copy this file into place
 *   node scripts/ci/docker-config.js --remove            delete it (new-system setup wizard)
 *
 * A Config.php that does not carry the docker development password is a real
 * install's configuration. It is copied to Config.php.<timestamp>.bak first.
 *
 * Every docker-prepared stack also carries the shared development system ID
 * in src/Include/SystemID.php, so dev, CI and GitHub testing systems report
 * as one known install (dev-churchcrm-testing, the same ID cypress/data/
 * seed.sql seeds) and fleet reporting can exclude them. A SystemID.php
 * holding any other ID is backed up first, like a real Config.php.
 */

const fs = require('fs');
const path = require('path');

const target = path.join(__dirname, '..', '..', 'src', 'Include', 'Config.php');
const systemIdTarget = path.join(__dirname, '..', '..', 'src', 'Include', 'SystemID.php');
const DEV_SYSTEM_ID = 'dev-churchcrm-testing';
const arg = process.argv[2];

function plantDevSystemId() {
    const contents = `<?php\n\nreturn '${DEV_SYSTEM_ID}';\n`;
    if (fs.existsSync(systemIdTarget)) {
        if (fs.readFileSync(systemIdTarget, 'utf8').includes(`'${DEV_SYSTEM_ID}'`)) {
            return;
        }
        const stamp = new Date().toISOString().replace(/\D/g, '').slice(0, 14);
        const backup = `${systemIdTarget}.${stamp}.bak`;
        fs.copyFileSync(systemIdTarget, backup, fs.constants.COPYFILE_EXCL);
        fs.chmodSync(backup, 0o600);
        console.warn(`⚠ ${path.relative(process.cwd(), systemIdTarget)} does not hold the shared dev system ID. Backed up to ${path.relative(process.cwd(), backup)}`);
    }
    fs.writeFileSync(systemIdTarget, contents);
}

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
    plantDevSystemId();
} else if (arg) {
    backUpRealConfig();
    fs.copyFileSync(path.resolve(arg), target);
    plantDevSystemId();
} else {
    console.error('usage: docker-config.js <source-file> | --remove');
    process.exit(1);
}
