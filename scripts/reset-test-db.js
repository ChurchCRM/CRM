#!/usr/bin/env node

/**
 * Returns the Cypress test database to its starting state: an empty database, the schema from
 * src/mysql/install/Install.sql, then the test data from cypress/data/seed.sql. The same two
 * files initialise a fresh test stack (docker/docker-compose.yaml mounts them as 00-install.sql
 * and 10-seed.sql).
 */

const { spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const CLIENT = 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql -u"$MYSQL_USER"';

const STEPS = [
    { script: `${CLIENT} -e "DROP DATABASE IF EXISTS $MYSQL_DATABASE; CREATE DATABASE $MYSQL_DATABASE"` },
    { script: `${CLIENT} "$MYSQL_DATABASE"`, file: 'src/mysql/install/Install.sql' },
    { script: `${CLIENT} "$MYSQL_DATABASE"`, file: 'cypress/data/seed.sql' },
];

function main() {
    for (const step of STEPS) {
        const result = spawnSync(
            'docker',
            ['compose', '-f', 'docker/docker-compose.yaml', '--profile', 'test', 'exec', '-T', 'database', 'sh', '-c', step.script],
            { cwd: root, input: step.file ? fs.readFileSync(path.join(root, step.file)) : undefined, stdio: ['pipe', 'inherit', 'inherit'] },
        );
        if (result.status !== 0) {
            process.exit(result.status || 1);
        }
    }
}

if (require.main === module) {
    main();
}

module.exports = { STEPS };
