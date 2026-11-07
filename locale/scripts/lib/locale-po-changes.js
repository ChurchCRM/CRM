'use strict';

const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '../../..');
const PO_FILE = 'locale/messages.po';

// Header timestamps bump on every build; a diff of only those lines is not a change.
function meaningfulChanges(diff) {
    return diff
        .split('\n')
        .filter((line) => /^\+[^+]|^-[^-]/.test(line))
        .filter((line) => !/^[+-]"(POT-Creation-Date|PO-Revision-Date):/.test(line)).length;
}

if (require.main === module) {
    const git = (...args) => execFileSync('git', args, { cwd: ROOT, encoding: 'utf8', maxBuffer: 256 * 1024 * 1024 });
    const changes = fs.existsSync(path.join(ROOT, PO_FILE)) ? meaningfulChanges(git('diff', '--', PO_FILE)) : 0;
    const output = process.env.GITHUB_OUTPUT;
    if (output) {
        fs.appendFileSync(output, `has_changes=${changes > 0}\n`);
    }
    console.log(changes > 0 ? `Meaningful changes detected: ${changes} lines` : `No meaningful changes in ${PO_FILE}`);
}

module.exports = { meaningfulChanges };
