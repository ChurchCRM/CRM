#!/usr/bin/env node

/**
 * node scripts/clean.js          remove build output, dependencies, and logs
 * node scripts/clean.js --logs   remove src/logs/*.log only
 */

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const logsDir = path.join(root, 'src', 'logs');
const removeDirs = ['node_modules', 'temp', 'src/vendor', 'src/skin/external', 'src/locale/vendor', 'ai-notes'];

function removeLogs() {
    if (!fs.existsSync(logsDir)) {
        return;
    }
    for (const name of fs.readdirSync(logsDir)) {
        if (name.endsWith('.log')) {
            fs.rmSync(path.join(logsDir, name), { force: true });
        }
    }
}

if (!process.argv.includes('--logs')) {
    for (const dir of removeDirs) {
        fs.rmSync(path.join(root, dir), { recursive: true, force: true });
    }
}
removeLogs();
