const { describe, it, afterEach } = require('node:test');
const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const FILES = {
    '.github/workflows/build-test-nightly.yml': '# MySQL 1.0 LTS a\n# MariaDB 2.0 LTS b\n  db_image: ["mysql:1.0"]\n',
    'docker/README.md': '| `mariadb:2.0` |\n',
    'docker/docker-compose.yaml': '    image: mariadb:2.0\n',
    'docker/docker-compose.dev.yaml': '    image: mariadb:2.0\n',
    'docker/docker-compose.parallel.yaml': '    image: mariadb:2.0\n    image: mariadb:2.0\n',
    'docker/docker-compose.mysql.yaml': '# default mysql:1.0)\n    image: ${MYSQL_IMAGE:-mysql:1.0}\n',
    'src/setup/templates/setup-steps.php': 'supports MySQL 1.0+ and MariaDB 2.0+ (the LTS releases)\n',
};
const trees = [];

function makeTree(versions = { mysql: '8.4', mariadb: '10.11' }) {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'crm-databases-'));
    trees.push(root);
    fs.mkdirSync(path.join(root, 'scripts'));
    fs.copyFileSync(path.join(__dirname, 'sync-supported-databases.js'), path.join(root, 'scripts', 'sync-supported-databases.js'));
    fs.writeFileSync(path.join(root, 'supported-databases.json'), JSON.stringify(versions));
    for (const [file, content] of Object.entries(FILES)) {
        fs.mkdirSync(path.dirname(path.join(root, file)), { recursive: true });
        fs.writeFileSync(path.join(root, file), content);
    }
    return root;
}

const run = (root, ...args) =>
    spawnSync(process.execPath, [path.join(root, 'scripts', 'sync-supported-databases.js'), ...args], { cwd: root, encoding: 'utf8' });
const read = (root, file) => fs.readFileSync(path.join(root, file), 'utf8');

afterEach(() => {
    for (const root of trees.splice(0)) {
        fs.rmSync(root, { recursive: true, force: true });
    }
});

describe('sync-supported-databases.js', () => {
    it('--check fails and names every out-of-date file', () => {
        const root = makeTree();
        const result = run(root, '--check');
        assert.equal(result.status, 1);
        for (const file of Object.keys(FILES)) {
            assert.match(result.stderr, new RegExp(file.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
        }
        assert.equal(read(root, 'docker/README.md'), FILES['docker/README.md']);
    });

    it('rewrites every file to the versions in the JSON, and --check then passes', () => {
        const root = makeTree();
        assert.equal(run(root).status, 0);
        assert.equal(read(root, 'docker/docker-compose.parallel.yaml'), '    image: mariadb:10.11\n    image: mariadb:10.11\n');
        assert.equal(read(root, 'docker/docker-compose.mysql.yaml'), '# default mysql:8.4)\n    image: ${MYSQL_IMAGE:-mysql:8.4}\n');
        assert.equal(read(root, 'src/setup/templates/setup-steps.php'), 'supports MySQL 8.4+ and MariaDB 10.11+ (the LTS releases)\n');
        assert.equal(read(root, '.github/workflows/build-test-nightly.yml'), '# MySQL 8.4 LTS a\n# MariaDB 10.11 LTS b\n  db_image: ["mysql:8.4"]\n');
        assert.equal(run(root, '--check').status, 0);
    });

    it('fails when a target no longer contains its version', () => {
        const root = makeTree();
        fs.writeFileSync(path.join(root, 'docker/README.md'), 'no database here\n');
        const result = run(root);
        assert.equal(result.status, 1);
        assert.match(result.stderr, /docker\/README\.md: no mariadb version found/);
    });

    it('rejects a version that is not a number', () => {
        const root = makeTree({ mysql: 'latest', mariadb: '10.11' });
        const result = run(root, '--check');
        assert.equal(result.status, 1);
        assert.match(result.stderr, /"mysql" must be a version/);
    });
});
