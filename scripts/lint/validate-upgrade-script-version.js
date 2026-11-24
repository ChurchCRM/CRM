#!/usr/bin/env node

/**
 * New database upgrade scripts must belong to the next release, never to one
 * that already shipped. An install only runs the scripts of the upgrade.json
 * step that matches its current DB version, so a script added to a shipped
 * step — or named for a shipped version — never runs anywhere, and the first
 * page that touches the missing column or table fails with a 500 (the 7.8.6
 * family-page incident, #11874: 7.8.5-recurring-pledges.sql was registered in
 * the already-shipped pre-7.8.5 step during the 7.8.6 cycle).
 *
 * The next release version is the `dbVersion` of the `current` step in
 * src/mysql/upgrade.json, read at check time.
 *
 * Fails (exit 1):
 *   - an added file under src/mysql/upgrade/ whose name starts with a version
 *     other than the next release
 *   - a script added to any upgrade.json step other than `current`
 *   - a script registered in `current` whose file name starts with a version
 *     other than the next release
 *
 * Modes: `--staged` checks the staged changes (pre-commit); the default mode
 * diffs against the merge-base with origin/master (or master), like
 * validate-pr-gates.js, and passes quietly when neither ref exists.
 */

const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..', '..');
const UPGRADE_DIR = 'src/mysql/upgrade/';
const UPGRADE_JSON = 'src/mysql/upgrade.json';
const VERSION_TOKEN = /^(\d+\.\d+\.\d+)/;

const git = (...args) => execFileSync('git', args, { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });

function gitOrNull(...args) {
    try {
        return git(...args);
    } catch {
        return null;
    }
}

// `git diff --name-status` lines: "A\tpath", "M\tpath", "R100\told\tnew".
function addedFromNameStatus(output) {
    return output
        .split('\n')
        .filter(Boolean)
        .flatMap((line) => {
            const fields = line.split('\t');
            const status = fields[0];
            if (status === 'A') {
                return [fields[1]];
            }
            if (status.startsWith('R') || status.startsWith('C')) {
                return [fields[2]];
            }
            return [];
        });
}

function parseUpgradeJson(text) {
    if (!text) {
        return null;
    }
    try {
        return JSON.parse(text);
    } catch {
        return null;
    }
}

function scriptSets(upgradeJson) {
    const sets = new Map();
    for (const [step, block] of Object.entries(upgradeJson || {})) {
        sets.set(step, new Set(Array.isArray(block?.scripts) ? block.scripts : []));
    }
    return sets;
}

function versionOf(fileName) {
    const match = VERSION_TOKEN.exec(path.posix.basename(fileName));
    return match ? match[1] : null;
}

function checkUpgradeScriptVersions({ addedFiles, baseJson, headJson }) {
    const problems = [];
    const nextVersion = headJson?.current?.dbVersion;
    if (!nextVersion) {
        return [`Could not read the next release version: src/mysql/upgrade.json has no "current" step with a dbVersion.`];
    }

    for (const file of addedFiles) {
        if (!file.startsWith(UPGRADE_DIR) || !/\.(sql|php)$/.test(file)) {
            continue;
        }
        const version = versionOf(file);
        if (version !== nextVersion) {
            const ext = path.posix.extname(file);
            const slug = path.posix.basename(file).replace(VERSION_TOKEN, '').replace(/^-/, '').slice(0, -ext.length || undefined) || 'change';
            problems.push(
                `${file} is named for ${version ?? 'no version'}, but the next release is ${nextVersion}. ` +
                    `Rename it to start with ${nextVersion} (for example ${nextVersion}-${slug}${ext}) ` +
                    `and register it in the "current" step of src/mysql/upgrade.json.`,
            );
        }
    }

    const baseSets = scriptSets(baseJson);
    const headSets = scriptSets(headJson);
    for (const [step, scripts] of headSets) {
        const before = baseSets.get(step) ?? new Set();
        for (const script of scripts) {
            if (before.has(script)) {
                continue;
            }
            if (step !== 'current') {
                problems.push(
                    `src/mysql/upgrade.json adds ${script} to the "${step}" step, which has already shipped. ` +
                        `Installs that have passed "${step}" will never run it. Move the entry to the "current" step instead.`,
                );
            } else if (versionOf(script) !== nextVersion) {
                problems.push(
                    `src/mysql/upgrade.json registers ${script} in the "current" step, but the next release is ${nextVersion}. ` +
                        `Rename the file to start with ${nextVersion} and update the entry to match.`,
                );
            }
        }
    }
    return problems;
}

function touchesUpgrade(addedFiles, baseJson, headJson) {
    if (addedFiles.some((file) => file.startsWith(UPGRADE_DIR))) {
        return true;
    }
    const baseSets = scriptSets(baseJson);
    for (const [step, scripts] of scriptSets(headJson)) {
        const before = baseSets.get(step) ?? new Set();
        if ([...scripts].some((script) => !before.has(script))) {
            return true;
        }
    }
    return false;
}

function stagedChanges() {
    const addedFiles = addedFromNameStatus(git('diff', '--cached', '--name-status'));
    const headJson = parseUpgradeJson(gitOrNull('show', `:${UPGRADE_JSON}`));
    const baseJson = parseUpgradeJson(gitOrNull('show', `HEAD:${UPGRADE_JSON}`));
    return { addedFiles, baseJson, headJson };
}

function branchChanges() {
    for (const ref of ['origin/master', 'master']) {
        const base = gitOrNull('merge-base', 'HEAD', ref)?.trim();
        if (!base) {
            continue;
        }
        const addedFiles = [
            ...addedFromNameStatus(git('diff', '--name-status', base)),
            ...git('ls-files', '--others', '--exclude-standard').split('\n').filter(Boolean),
        ];
        let headText = null;
        try {
            headText = fs.readFileSync(path.join(root, UPGRADE_JSON), 'utf8');
        } catch {
            // upgrade.json unreadable: the checks below report it if relevant
        }
        return { addedFiles, baseJson: parseUpgradeJson(gitOrNull('show', `${base}:${UPGRADE_JSON}`)), headJson: parseUpgradeJson(headText) };
    }
    return null;
}

if (require.main === module) {
    const changes = process.argv.includes('--staged') ? stagedChanges() : branchChanges();
    if (!changes) {
        process.exit(0);
    }
    const { addedFiles, baseJson, headJson } = changes;
    if (!touchesUpgrade(addedFiles, baseJson, headJson)) {
        const nextVersion = headJson?.current?.dbVersion;
        console.log(`No new upgrade scripts${nextVersion ? ` (next release: ${nextVersion})` : ''}.`);
        process.exit(0);
    }
    const problems = checkUpgradeScriptVersions(changes);
    problems.forEach((message) => console.error(message));
    if (!problems.length) {
        console.log(`Upgrade scripts match the next release (${headJson.current.dbVersion}).`);
    }
    process.exit(problems.length ? 1 : 0);
}

module.exports = { checkUpgradeScriptVersions, versionOf };
