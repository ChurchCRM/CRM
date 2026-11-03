const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

const root = path.join(__dirname, '..');

function options(args, repo = root) {
    const [suite, ...rest] = args;
    if (!['unit', 'integration'].includes(suite)) {
        throw new Error('Usage: node scripts/test-php.js unit|integration [package-or-file] [--junit]');
    }
    const targets = rest.filter((arg) => arg !== '--junit');
    if (targets.length > 1 || targets.some((arg) => arg.startsWith('-'))) {
        throw new Error('Choose one package or test file relative to the suite directory.');
    }
    const base = path.join(repo, 'tests', 'php', suite);
    const target = path.resolve(base, targets[0] || '.');
    const relative = path.relative(base, target);
    if (relative.startsWith('..') || path.isAbsolute(relative)) {
        throw new Error('The test target must be inside its suite.');
    }
    if (!fs.existsSync(target) || (fs.statSync(target).isFile() && !target.endsWith('Test.php'))) {
        throw new Error(`Unknown PHP test package or file: ${targets[0] || suite}`);
    }
    return { suite, target, junit: rest.includes('--junit') };
}

function run(selected, { repo = root, env = process.env, spawn = spawnSync } = {}) {
    const tester = path.join(repo, 'tests', 'php', 'vendor', 'bin', 'tester');
    for (const file of [tester, path.join(repo, 'src', 'vendor', 'autoload.php')]) {
        if (!fs.existsSync(file)) {
            throw new Error('Run npm run build:php and npm run test:php:install before PHP tests.');
        }
    }
    if (selected.suite === 'integration' && (env.PHP_TEST_DISPOSABLE_DB !== '1' || !env.UPGRADE_TEST_DSN || !env.UPGRADE_TEST_USER || !env.UPGRADE_TEST_PASSWORD)) {
        throw new Error('Integration tests require PHP_TEST_DISPOSABLE_DB=1 and UPGRADE_TEST_DSN/USER/PASSWORD.');
    }
    let isolated;
    try {
        const childEnv = { ...env, PHP_TEST_APP_ROOT: path.join(repo, 'src'), PHP_TEST_ISOLATED: '0' };
        if (selected.suite === 'integration') {
            isolated = fs.mkdtempSync(path.join(os.tmpdir(), 'churchcrm-php-'));
            childEnv.PHP_TEST_APP_ROOT = path.join(isolated, 'src');
            childEnv.PHP_TEST_ISOLATED = '1';
            fs.cpSync(path.join(repo, 'src'), childEnv.PHP_TEST_APP_ROOT, {
                recursive: true,
                dereference: true,
                filter: (file) => {
                    const relative = path.relative(path.join(repo, 'src'), file).replaceAll(path.sep, '/');
                    return !/^Include\/Config\.php(?:\.|$)/.test(relative) && !/^(?:logs|Images\/(?:Person|Family))(?:\/|$)/.test(relative);
                },
            });
            fs.mkdirSync(path.join(childEnv.PHP_TEST_APP_ROOT, 'logs'), { recursive: true });
        }
        const args = [tester, '-j', '1', '--timeout', selected.suite === 'unit' ? '120' : '300', '-o', 'console-lines'];
        if (selected.junit) {
            const reports = path.join(repo, 'temp', 'php-tests');
            fs.mkdirSync(reports, { recursive: true });
            args.push('-o', `junit:${path.join(reports, `${selected.suite}.xml`)}`);
        }
        args.push(selected.target);
        const result = spawn('php', args, { cwd: repo, env: childEnv, stdio: 'inherit' });
        if (result.error) throw result.error;
        return result.status ?? 1;
    } finally {
        if (isolated) fs.rmSync(isolated, { recursive: true, force: true });
    }
}

if (require.main === module) {
    try {
        process.exitCode = run(options(process.argv.slice(2)));
    } catch (error) {
        console.error(error.message);
        process.exitCode = 2;
    }
}

module.exports = { options, run };
