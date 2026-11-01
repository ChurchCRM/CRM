#!/usr/bin/env node

const { execFileSync, spawnSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

function validateComposerLock(root, staged = false, relative = 'src') {
    const files = [`${relative}/composer.json`, `${relative}/composer.lock`];
    if (staged) {
        const changed = execFileSync('git', ['diff', '--cached', '--name-only', '--', ...files], { cwd: root, encoding: 'utf8' });
        if (!changed.trim()) {
            return 0;
        }
    }

    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'churchcrm-composer-'));
    try {
        for (const file of files) {
            const content = staged
                ? execFileSync('git', ['show', `:${file}`], { cwd: root })
                : fs.readFileSync(path.join(root, file));
            fs.writeFileSync(path.join(directory, path.basename(file)), content);
        }
        const result = spawnSync('composer', [
            '--no-plugins', '--no-scripts', '--no-interaction', 'validate',
            '--strict', '--no-check-publish', '--no-check-version', '--check-lock',
        ], { cwd: directory, encoding: 'utf8', env: { ...process.env, COMPOSER_DISABLE_NETWORK: '1' } });
        if (result.status !== 0) {
            process.stderr.write(result.error ? `${result.error.message}\n` : `${result.stdout}${result.stderr}`);
            process.stderr.write(`Refresh with composer update --lock --no-install --working-dir=${relative}, then stage both Composer files.\n`);
        }
        return result.status ?? 1;
    } finally {
        fs.rmSync(directory, { recursive: true, force: true });
    }
}

if (require.main === module) {
    try {
        const results = ['src', 'tests/php'].map((directory) => validateComposerLock(path.join(__dirname, '..'), process.argv.includes('--staged'), directory));
        process.exitCode = results.some((status) => status !== 0) ? 1 : 0;
    } catch (error) {
        console.error(error.message);
        process.exitCode = 1;
    }
}

module.exports = { validateComposerLock };
