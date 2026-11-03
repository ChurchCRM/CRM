#!/usr/bin/env node

const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

const root = path.join(__dirname, '..');
const modes = ['static', 'php', 'integration'];

function options(args) {
    const mode = args.find((arg) => modes.includes(arg)) || 'static';
    if (args.some((arg) => !modes.includes(arg) && arg !== '--services') || args.filter((arg) => modes.includes(arg)).length > 1) {
        throw new Error('Usage: npm run env:check -- [static|php|integration] [--services]');
    }
    if (args.includes('--services') && mode !== 'integration') {
        throw new Error('--services requires integration mode');
    }
    return { mode, services: args.includes('--services') };
}

function command(bin, args) {
    return spawnSync(bin, args, { cwd: root, encoding: 'utf8', timeout: 30000, windowsHide: true });
}

function checkEnvironment({ mode, services }, run = command, exists = (file) => fs.existsSync(path.join(root, file))) {
    const results = [];
    const check = (name, ok, remedy) => results.push({ name, ok: Boolean(ok), remedy });
    const succeeds = (bin, args) => run(bin, args).status === 0;
    const nodeMinimum = Number(require('../package.json').engines.node.match(/\d+/)[0]);
    const nodeVersion = run(process.execPath, ['--version']);
    check('Node runtime', nodeVersion.status === 0 && Number(nodeVersion.stdout.trim().replace(/^v/, '').split('.')[0]) >= nodeMinimum,
        `Install Node ${nodeMinimum}+ (see .nvmrc).`);
    check('Git', succeeds('git', ['--version']), 'Install Git.');
    check('PHP runtime', succeeds('php', ['-r', 'exit(version_compare(PHP_VERSION, "8.4", ">=") ? 0 : 1);']),
        'Install PHP 8.4+ or use bash scripts/setup-dev.sh --docker.');
    check('Composer', succeeds('composer', ['--version']), 'Install Composer or use the dev image.');
    for (const [name, bin, args] of [
        ['Biome', 'node_modules/@biomejs/biome/bin/biome', ['--version']],
        ['TypeScript', 'node_modules/typescript/bin/tsc', ['--version']],
    ]) {
        check(name, exists(bin) && succeeds(process.execPath, [path.join(root, bin), ...args]),
            'Run npm ci online, retaining dev and optional dependencies for this OS/architecture.');
    }
    if (mode !== 'static') {
        const manifest = require('../src/composer.json');
        const extensions = [...new Set([
            ...Object.keys(manifest.require).filter((key) => key.startsWith('ext-')).map((key) => key.slice(4).toLowerCase()),
            'pdo_mysql', 'pcre', 'phar', 'simplexml',
        ])];
        const code = `exit(count(array_filter(${JSON.stringify(extensions)}, fn($ext) => !extension_loaded($ext))) ? 1 : 0);`;
        check(`PHP extensions (${extensions.join(', ')})`, succeeds('php', ['-r', code]),
            'Enable the listed extensions in CLI PHP; docker/Dockerfile.churchcrm-apache-php8 supplies them.');
        check('Composer vendor autoloader', exists('src/vendor/autoload.php'), 'Run npm run build:php online.');
        check('PHP test runner', exists('tests/php/vendor/bin/tester'), 'Run npm run test:php:install online.');
        check('PHP test platform requirements', succeeds('composer', ['--working-dir=tests/php', 'check-platform-reqs']),
            'Run npm run test:php:install and enable missing CLI extensions.');
        check('Composer platform requirements', succeeds('composer', ['--working-dir=src', 'check-platform-reqs', '--no-dev']),
            'Run npm run build:php and enable missing extensions; run composer --working-dir=src check-platform-reqs --no-dev for details.');
    }
    if (mode === 'integration') {
        check('Docker daemon', succeeds('docker', ['info']), 'Provide a running Docker Engine accessible to this user. The CLI alone is insufficient.');
        check('Compose v2', succeeds('docker', ['compose', 'version']), 'Install Docker Compose v2.');
        check('Cypress binary and OS libraries', exists('node_modules/cypress/bin/cypress') && succeeds(process.execPath, [path.join(root, 'node_modules/cypress/bin/cypress'), 'verify']),
            'Run bash scripts/setup-dev.sh --integration online; read the Cypress error for missing OS libraries.');
        check('Built frontend', exists('src/skin/v2/churchcrm.min.js'), 'Run npm run build.');
        check('Build signatures', exists('src/admin/data/signatures.json'), 'Run npm run build.');
        if (services) {
            const compose = ['compose', '-f', 'docker/docker-compose.yaml', '--profile', 'test'];
            const running = run('docker', [...compose, 'ps', '--services', '--status', 'running']);
            const names = (running.stdout || '').trim().split(/\s+/);
            check('Test services running', running.status === 0 && ['database', 'webserver', 'mailserver'].every((name) => names.includes(name)),
                'Run npm run docker:test:start (stop the dev stack first if it uses the same ports).');
            check('Database healthy', succeeds('docker', [...compose, 'exec', '-T', 'database', 'healthcheck.sh', '--connect', '--innodb_initialized']),
                'Inspect npm run docker:test:logs; wait for database initialization.');
            check('HTTP login page', succeeds('curl', ['--fail', '--silent', '--show-error', '--max-time', '10', '--output', '/dev/null', process.env.CYPRESS_BASE_URL ? `${process.env.CYPRESS_BASE_URL.replace(/\/$/, '')}/session/begin` : 'http://localhost/session/begin']),
                'Check webserver logs and CYPRESS_BASE_URL if using a non-default port.');
        }
    }
    return results;
}

if (require.main === module) {
    try {
        const selected = options(process.argv.slice(2));
        const results = checkEnvironment(selected);
        for (const result of results) {
            console.log(`${result.ok ? 'PASS' : 'FAIL'} ${result.name}${result.ok ? '' : `: ${result.remedy}`}`);
        }
        const failed = results.filter((result) => !result.ok).length;
        console.log(`${selected.mode}: ${results.length - failed}/${results.length} checks passed. No application tests were run.`);
        process.exitCode = failed ? 1 : 0;
    } catch (error) {
        console.error(error.message);
        process.exitCode = 2;
    }
}

module.exports = { options, checkEnvironment };
