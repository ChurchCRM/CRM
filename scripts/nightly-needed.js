#!/usr/bin/env node

/**
 * A scheduled nightly run starts with this check. It runs the suite only when something the suite
 * tests or ships changed since the last successful nightly run on the branch. Otherwise the run
 * ends green without starting the test jobs.
 *
 * "Tested or shipped" is the push `paths:` list of build-test-package.yml, so the two workflows
 * agree. Translations count: the nightly runs the locale sweep.
 *
 * Skipping is safe because the baseline is the last successful run, and a run succeeds without
 * testing only when every file changed since that baseline is ignorable. A failed run is never the
 * baseline, so a failed commit keeps getting retried.
 *
 * Manual, release and release-branch runs always run.
 */

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const WORKFLOW = 'build-test-nightly.yml';
const PACKAGE_WORKFLOW = path.join(__dirname, '..', '.github', 'workflows', 'build-test-package.yml');
// The compare API returns at most 300 files; a bigger change is treated as testable.
const MAX_COMPARE_FILES = 300;

function pushPatterns(workflowText) {
    const block = workflowText.match(/\n {2}push:[\s\S]*?\n {4}paths:\n([\s\S]*?)\n {2}workflow_dispatch:/);
    if (!block) {
        throw new Error('push paths: not found in build-test-package.yml');
    }
    return [...block[1].matchAll(/^\s+- '([^']+)'/gm)].map((match) => match[1]);
}

function globToRegExp(glob) {
    let source = '';
    for (let i = 0; i < glob.length; i += 1) {
        if (glob.startsWith('**/', i)) {
            source += '(?:.*/)?';
            i += 2;
        } else if (glob.startsWith('**', i)) {
            source += '.*';
            i += 1;
        } else if (glob[i] === '*') {
            source += '[^/]*';
        } else {
            source += glob[i].replace(/[.+^${}()|[\]\\?]/g, '\\$&');
        }
    }
    return new RegExp(`^${source}$`);
}

// GitHub applies the patterns in order: a later match overrides an earlier one.
function isTestable(file, patterns) {
    let testable = false;
    for (const pattern of patterns) {
        const negated = pattern.startsWith('!');
        if (globToRegExp(negated ? pattern.slice(1) : pattern).test(file)) {
            testable = !negated;
        }
    }
    return testable;
}

function decide({ event, sha, lastSha, changedFiles, patterns }) {
    if (event !== 'schedule') {
        return { run: true, reason: `${event} runs always run` };
    }
    if (!lastSha) {
        return { run: true, reason: 'no earlier successful run to compare with' };
    }
    if (lastSha === sha) {
        return { run: false, reason: `${sha.slice(0, 12)} already passed` };
    }
    if (changedFiles === null || changedFiles.length >= MAX_COMPARE_FILES) {
        return { run: true, reason: 'the change since the last green run could not be listed' };
    }
    const testable = changedFiles.find((file) => isTestable(file, patterns));
    if (testable) {
        return { run: true, reason: `${testable} changed since ${lastSha.slice(0, 12)}` };
    }
    return { run: false, reason: `only files the suite ignores changed since ${lastSha.slice(0, 12)}` };
}

function gh(args) {
    return JSON.parse(execFileSync('gh', ['api', ...args], { encoding: 'utf8' }));
}

function main() {
    const { EVENT, SHA, BRANCH, REPO } = process.env;
    let decision;
    try {
        const patterns = pushPatterns(fs.readFileSync(PACKAGE_WORKFLOW, 'utf8'));
        let lastSha = null;
        let changedFiles = null;
        if (EVENT === 'schedule') {
            const runs = gh([`repos/${REPO}/actions/workflows/${WORKFLOW}/runs?branch=${BRANCH}&status=success&per_page=1`]);
            lastSha = runs.workflow_runs[0]?.head_sha ?? null;
            if (lastSha && lastSha !== SHA) {
                try {
                    changedFiles = gh([`repos/${REPO}/compare/${lastSha}...${SHA}`]).files.map((file) => file.filename);
                } catch {
                    changedFiles = null;
                }
            }
        }
        decision = decide({ event: EVENT, sha: SHA, lastSha, changedFiles, patterns });
    } catch (error) {
        decision = { run: true, reason: `check failed (${error.message}); running` };
    }

    console.log(`${decision.run ? 'Run' : 'Skip'}: ${decision.reason}`);
    if (process.env.GITHUB_OUTPUT) {
        fs.appendFileSync(process.env.GITHUB_OUTPUT, `run=${decision.run}\nreason=${decision.reason}\n`);
    }
}

if (require.main === module) {
    main();
}

module.exports = { decide, globToRegExp, isTestable, pushPatterns };
