#!/usr/bin/env node

/**
 * Local copy of the PR gates that CodeRabbit and the maintainer review enforce, so a
 * contributor learns about them before the push instead of from a review thread.
 *
 * Fails (exit 1):
 *   - the Propel schema or Install.sql changed without a src/mysql/upgrade/ script and upgrade.json
 *   - a workflow or action passes untrusted GitHub context straight into a `run:` script
 * Warns (exit 0, printed): behavior changes without a Cypress spec or script test, a changed
 * workflow without a script test, and UI files that need the light/dark, tablet/phone pass.
 *
 * Mark a deliberate `run:` interpolation with  # inject-ok: <reason>
 * The diff is taken against origin/master (or master); without either ref only the
 * workflow scan runs.
 */

const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');

const UNTRUSTED =
    /\$\{\{\s*(?:github\.head_ref|github\.event\.(?:pull_request\.(?:title|body|head\.(?:ref|label))|issue\.(?:title|body)|comment\.body|review\.body|head_commit\.(?:message|author\.(?:name|email))|commits\[[^\]]*\]\.(?:message|author\.(?:name|email)))|github\.event\.workflow_run\.head_branch)/;

const isSchema = (file) => file === 'orm/schema.xml' || file === 'src/mysql/install/Install.sql';
const isUpgrade = (file) => file.startsWith('src/mysql/upgrade/') || file === 'src/mysql/upgrade.json';
const isWorkflow = (file) => /^\.github\/(workflows|actions)\//.test(file);
const isTest = (file) => file.startsWith('cypress/') || /^scripts\/.*\.test\.js$/.test(file) || /^locale\/scripts\/.*\.test\.js$/.test(file);
const isBehavior = (file) =>
    (/^src\/.*\.(php|twig)$/.test(file) && !/^src\/(vendor|mysql|locale)\//.test(file)) ||
    (/^webpack\/.*\.(js|ts|tsx)$/.test(file) && !file.endsWith('.test.js'));
const isUi = (file) => /^src\/skin\/|^webpack\/|\.(twig)$/.test(file) || /^src\/.*\/(views|templates)\//.test(file);

function injections(source) {
    let runIndent = -1;
    return source.split('\n').flatMap((line, index) => {
        const indent = line.length - line.trimStart().length;
        if (runIndent >= 0 && line.trim() && indent <= runIndent) {
            runIndent = -1;
        }
        const starts = /^\s*(?:-\s+)?run:\s*(.*)$/.exec(line);
        const inRun = runIndent >= 0 || (starts && starts[1] !== '');
        if (starts && /^[|>][-+]?\s*$/.test(starts[1])) {
            runIndent = indent + (/^\s*-\s/.test(line) ? 2 : 0);
        }
        return inRun && UNTRUSTED.test(line) && !/inject-ok:/.test(line) ? [{ line: index + 1, text: line.trim() }] : [];
    });
}

function gates(files) {
    const fail = [];
    const warn = [];
    if (files.some(isSchema) && !(files.some((f) => f.startsWith('src/mysql/upgrade/')) && files.includes('src/mysql/upgrade.json'))) {
        fail.push('Schema changed without a src/mysql/upgrade/ script and an upgrade.json entry.');
    }
    const behavior = files.filter(isBehavior);
    if (behavior.length && !files.some(isTest)) {
        warn.push(`Behavior changed (${behavior[0]}${behavior.length > 1 ? ` +${behavior.length - 1}` : ''}) with no Cypress spec or script test. Name the spec that covers it in the PR body.`);
    }
    const workflows = files.filter(isWorkflow);
    if (workflows.length && !files.some((f) => /^scripts\/.*\.test\.js$/.test(f))) {
        warn.push(`Workflow or action changed (${workflows[0]}) with no scripts/*.test.js. Assert job dependencies, refs and failure handling.`);
    }
    if (files.some(isUi)) {
        warn.push('UI files changed. Check light and dark at desktop, ~820px and phone width, then state the pass in the PR body.');
    }
    return { fail, warn };
}

function workflowFiles() {
    return ['workflows', 'actions'].flatMap((dir) => {
        const walk = (d) =>
            fs.existsSync(d)
                ? fs.readdirSync(d, { withFileTypes: true }).flatMap((e) => {
                      const p = path.join(d, e.name);
                      return e.isDirectory() ? walk(p) : /\.ya?ml$/.test(e.name) ? [p] : [];
                  })
                : [];
        return walk(path.join(root, '.github', dir));
    });
}

function changedFiles() {
    const git = (...args) => execFileSync('git', args, { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
    for (const ref of ['origin/master', 'master']) {
        try {
            const base = git('merge-base', 'HEAD', ref);
            return [...git('diff', '--name-only', base).split('\n'), ...git('ls-files', '--others', '--exclude-standard').split('\n')].filter(Boolean);
        } catch {
            // try the next ref
        }
    }
    return null;
}

if (require.main === module) {
    const problems = workflowFiles().flatMap((file) =>
        injections(fs.readFileSync(file, 'utf8')).map((hit) => `${path.relative(root, file)}:${hit.line}  untrusted context in run: ${hit.text}`),
    );
    const files = changedFiles();
    const { fail, warn } = files ? gates(files) : { fail: [], warn: [] };
    problems.push(...fail);
    warn.forEach((message) => console.log(`note: ${message}`));
    problems.forEach((message) => console.error(message));
    if (problems.length) {
        console.error('\nPass untrusted values through env: and quote "$VAR"; mark a safe case with # inject-ok: <reason>.');
    }
    process.exit(problems.length ? 1 : 0);
}

module.exports = { gates, injections };
