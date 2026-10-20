#!/usr/bin/env node

/**
 * Prepares src/Include/Config.php for a docker profile.
 *   node scripts/docker-config.js docker/Config.php   copy this file into place
 *   node scripts/docker-config.js --remove            delete it (new-system setup wizard)
 */

const fs = require('fs');
const path = require('path');

const target = path.join(__dirname, '..', 'src', 'Include', 'Config.php');
const arg = process.argv[2];

if (arg === '--remove') {
    fs.rmSync(target, { force: true });
} else if (arg) {
    fs.copyFileSync(path.resolve(arg), target);
} else {
    console.error('usage: docker-config.js <source-file> | --remove');
    process.exit(1);
}
