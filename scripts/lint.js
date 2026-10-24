#!/usr/bin/env node

/**
 * Runs every lint check in parallel and prints a summary. Output of a passing
 * check is hidden; a failing check prints its full output. Used by
 * `npm run lint`, the pre-push hook, and CI.
 */

const { execFileSync, spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const isWindows = process.platform === 'win32';
const node = process.execPath;
const script = (name, ...args) => [node, [path.join(__dirname, name), ...args]];
const npx = (...args) => [isWindows ? 'npx.cmd' : 'npx', ['--no-install', ...args]];
const localeTests = fs
    .readdirSync(path.join(root, 'locale', 'scripts', 'lib'))
    .filter((name) => name.endsWith('.test.js'))
    .map((name) => path.join('locale', 'scripts', 'lib', name));

const scriptTests = fs
    .readdirSync(__dirname)
    .filter((name) => name.endsWith('.test.js'))
    .map((name) => path.join('scripts', name));

const checks = {
    'biome lint': npx('biome', 'check', 'webpack/'),
    'biome format': npx('biome', 'format', '.'),
    typecheck: npx('tsc', '--noEmit', '--pretty'),
    'tabler icons': script('validate-no-tabler-icons.js'),
    'font awesome icons': script('validate-fa-icons-exist.js'),
    'ui conventions': script('validate-ui-conventions.js'),
    'pinned actions': script('validate-actions-pinned.js'),
    'cypress api keys': script('validate-cypress-api-keys.js'),
    'cypress commands': script('validate-cypress-commands.js'),
    'cypress spec coverage': script('validate-cypress-spec-coverage.js'),
    'subdir-safe urls': script('validate-subdir-urls.js'),
    'cypress split timings': script('split-timings.js', 'check'),
    'cypress db access': script('validate-cypress-db-access.js'),
    'capture used-in': script('validate-capture-used-in.js'),
    'seed data only': script('validate-seed-data-only.js'),
    'mysql upgrade syntax': script('validate-mysql-upgrade-syntax.js'),
    'upgrade locale list': script('validate-upgrade-locales.js'),
    'supported databases': script('sync-supported-databases.js', '--check'),
    'locale script tests': [node, ['--test', ...localeTests]],
    'tooling script tests': [node, ['--test', ...scriptTests]],
};

function run(name, [command, args]) {
    const started = Date.now();
    return new Promise((resolve) => {
        const child = spawn(command, args, { cwd: root, shell: isWindows });
        let output = '';
        child.stdout.on('data', (chunk) => {
            output += chunk;
        });
        child.stderr.on('data', (chunk) => {
            output += chunk;
        });
        child.on('error', (error) => {
            output += `${error.message}\n`;
            resolve({ name, ok: false, output, seconds: (Date.now() - started) / 1000 });
        });
        child.on('close', (code) => resolve({ name, ok: code === 0, output, seconds: (Date.now() - started) / 1000 }));
    });
}

(async () => {
    execFileSync(node, [path.join(__dirname, 'ensure-biome.js')], { cwd: root, stdio: 'inherit' });

    const failed = [];
    await Promise.all(
        Object.entries(checks).map(async ([name, command]) => {
            const result = await run(name, command);
            console.log(`${result.ok ? '✓' : '✖'} ${name} (${result.seconds.toFixed(1)}s)`);
            if (!result.ok) {
                failed.push(result);
            }
        }),
    );

    for (const result of failed) {
        console.error(`\n──── ${result.name} ────\n${result.output.trimEnd()}`);
    }
    const total = Object.keys(checks).length;
    console.log(failed.length ? `\n✖ ${failed.length} of ${total} checks failed.` : `\n✓ All ${total} checks passed.`);
    process.exit(failed.length ? 1 : 0);
})();
