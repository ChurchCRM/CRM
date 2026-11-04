#!/usr/bin/env node

/**
 * Installs production Composer dependencies into src/vendor and refreshes the
 * root package version and autoloader on every build. Matching dependencies
 * skip validation and vendor stripping.
 */

const { spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const srcDir = path.join(__dirname, '..', 'src');
const lockFile = path.join(srcDir, 'composer.lock');
const installedFile = path.join(srcDir, 'vendor', 'composer', 'installed.json');
const strippedDirs = new Set(['tests', 'Tests', 'test', '_test', 'docs', 'examples', 'example']);

function packageSet(packages) {
    return new Set(packages.map((p) => `${p.name}@${p.version}@${p.dist?.reference ?? p.source?.reference ?? ''}`));
}

function vendorIsCurrent() {
    if (!fs.existsSync(installedFile)) {
        return false;
    }
    const installed = JSON.parse(fs.readFileSync(installedFile, 'utf8'));
    if (installed.dev !== false) {
        return false;
    }
    const locked = packageSet(JSON.parse(fs.readFileSync(lockFile, 'utf8')).packages);
    const current = packageSet(installed.packages);
    return locked.size === current.size && [...locked].every((p) => current.has(p));
}

function composer(args) {
    const result = spawnSync('composer', args, { cwd: srcDir, stdio: 'inherit', shell: process.platform === 'win32' });
    if (result.error?.code === 'ENOENT') {
        console.error('\n✖ composer is not installed or not on PATH. Install it from https://getcomposer.org/download/\n');
        process.exit(1);
    }
    if (result.status !== 0) {
        process.exit(result.status ?? 1);
    }
}

function stripVendor(dir) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        if (!entry.isDirectory()) {
            continue;
        }
        const full = path.join(dir, entry.name);
        if (strippedDirs.has(entry.name)) {
            fs.rmSync(full, { recursive: true, force: true });
        } else {
            stripVendor(full);
        }
    }
}

if (vendorIsCurrent()) {
    console.log('✓ src/vendor matches composer.lock; refreshing Composer metadata.');
    composer(['install', '--no-dev']);
    process.exit(0);
}

composer(['validate', '--no-check-publish']);
composer(['install', '--no-dev']);
stripVendor(path.join(srcDir, 'vendor'));
