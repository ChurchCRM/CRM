#!/usr/bin/env node

const { execFileSync, spawnSync } = require('node:child_process');
const path = require('node:path');

const root = path.resolve(__dirname, '../../..');
const port = process.env.UPGRADE_BENCHMARK_PORT || '33318';
const project = `crm-upgrade-benchmark-${process.pid}`;
const composeFile = path.join(root, 'docker', 'docker-compose.yaml');
const composeEnv = { ...process.env, DATABASE_PORT: port };
const composeArgs = ['compose', '--project-name', project, '--file', composeFile, '--profile', 'test'];
const databaseNames = ['crm_upgrade_run1', 'crm_upgrade_run2', 'crm_upgrade_run3'];
let containerId;
let composeStarted = false;

function run(command, args, options = {}) {
    const result = spawnSync(command, args, { stdio: 'inherit', ...options });
    if (result.error) throw result.error;
    if (result.status !== 0) throw new Error(`${command} exited with status ${result.status}`);
    return result.stdout?.toString().trim() || '';
}

function benchmark(database, extraEnv = {}) {
    run('php', [path.join(__dirname, 'benchmark-large-upgrade.php')], {
        cwd: root,
        env: {
            ...process.env,
            UPGRADE_BENCHMARK_DSN: `mysql:host=127.0.0.1;port=${port};dbname=${database};charset=utf8mb4`,
            UPGRADE_BENCHMARK_HOST: '127.0.0.1',
            UPGRADE_BENCHMARK_PORT: port,
            UPGRADE_BENCHMARK_USER: 'churchcrm',
            UPGRADE_BENCHMARK_PASSWORD: 'changeme',
            UPGRADE_BENCHMARK_DATABASE: database,
            ...extraEnv,
        },
    });
}

async function main() {
    if (!require('node:fs').existsSync(path.join(root, 'src', 'vendor', 'autoload.php'))) {
        throw new Error('PHP dependencies are missing; run npm run build:php first.');
    }

    run('docker', [...composeArgs, 'up', '--detach', 'database'], { cwd: root, env: composeEnv });
    composeStarted = true;
    containerId = execFileSync('docker', [...composeArgs, 'ps', '--quiet', 'database'], { cwd: root, env: composeEnv, encoding: 'utf8' }).trim();
    if (!containerId) throw new Error('Compose did not return the database container ID.');

    let ready = false;
    for (let attempt = 0; attempt < 60; attempt++) {
        const health = spawnSync('docker', ['exec', containerId, 'healthcheck.sh', '--connect', '--innodb_initialized'], { stdio: 'ignore' });
        if (health.status === 0) {
            ready = true;
            break;
        }
        await new Promise((resolve) => setTimeout(resolve, 1000));
    }
    if (!ready) throw new Error('MariaDB did not become ready within 60 seconds.');

    const sql = [
        ...databaseNames.map((database) => `CREATE DATABASE ${database} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`),
        "CREATE USER IF NOT EXISTS 'churchcrm'@'%' IDENTIFIED BY 'changeme';",
        ...databaseNames.map((database) => `GRANT ALL PRIVILEGES ON ${database}.* TO 'churchcrm'@'%';`),
    ].join('\n');
    run('docker', ['exec', containerId, 'mariadb', '--user=root', '--password=changeme', '-e', sql], { cwd: root, env: composeEnv });

    for (const runNumber of [1, 2]) {
        const database = databaseNames[runNumber - 1];
        console.log(`RUN ${runNumber} — MariaDB 10.11 — database ${database}`);
        benchmark(database);
    }

    const recentDatabase = databaseNames[2];
    console.log('RUN 3 — prepare the 7.8.0 checkpoint for the separate recent path');
    benchmark(recentDatabase, { UPGRADE_BENCHMARK_STOP_AT: '7.8.0' });
    console.log('RECENT PATH — 7.8.0 to current');
    benchmark(recentDatabase, { UPGRADE_BENCHMARK_START_VERSION: '7.8.0', UPGRADE_BENCHMARK_RESUME: '1' });
}

main()
    .catch((error) => {
        console.error(error.message);
        process.exitCode = 1;
    })
    .finally(() => {
        if (composeStarted) {
            spawnSync('docker', [...composeArgs, 'down', '--volumes'], { cwd: root, env: composeEnv, stdio: 'ignore' });
        }
    });
