#!/usr/bin/env node

/**
 * Production build: Composer dependencies and webpack assets run in parallel,
 * then signatures.json is generated from the finished src/ tree.
 */

const { spawn } = require('child_process');

function npmRun(script) {
    return new Promise((resolve) => {
        const child = spawn('npm', ['run', script], { stdio: 'inherit', shell: process.platform === 'win32' });
        child.on('error', () => resolve(1));
        child.on('close', (code) => resolve(code ?? 1));
    });
}

(async () => {
    const codes = await Promise.all([npmRun('build:php'), npmRun('build:frontend')]);
    if (codes.some((code) => code !== 0)) {
        process.exit(1);
    }
    process.exit(await npmRun('build:signatures'));
})();
