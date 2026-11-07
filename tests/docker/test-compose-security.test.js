const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const { spawnSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const dockerDir = path.join(__dirname, '..', '..', 'docker');
const blockedPrefix = /^(DOCKER_BIND_|MYSQL_|DATABASE_|WEBSERVER_|MAILSERVER_)/;

function render(files, profile, environment) {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'compose-security-'));
    try {
        fs.writeFileSync(path.join(directory, '.env'), '');
        const args = ['compose'];
        for (const filename of files) {
            const source = path.join(dockerDir, filename);
            const target = path.join(directory, path.basename(source));
            fs.copyFileSync(source, target);
            args.push('-f', target);
        }
        if (profile) {
            args.push('--profile', profile);
        }
        args.push('config', '--format', 'json');
        const env = {};
        for (const [key, value] of Object.entries(process.env)) {
            if (!blockedPrefix.test(key) && !key.startsWith('CHURCHCRM_')) {
                env[key] = value;
            }
        }
        Object.assign(env, environment || {});
        return spawnSync('docker', args, { env, encoding: 'utf8' });
    } finally {
        fs.rmSync(directory, { recursive: true, force: true });
    }
}

function services(result, label) {
    assert.equal(result.status, 0, `${label}\n${result.stderr}`);
    return JSON.parse(result.stdout).services;
}

describe('compose development boundaries', () => {
    it('binds development ports to loopback and mounts seeds read-only', () => {
        const cases = [
            [['docker-compose.yaml'], 'test'],
            [['docker-compose.dev.yaml'], undefined],
            [['docker-compose.yaml', 'docker-compose.parallel.yaml'], 'ci-root'],
            [['docker-compose.yaml', 'docker-compose.parallel.yaml'], 'ci-subdir'],
            [['docker-compose.yaml', 'docker-compose.parallel.yaml'], 'ci-new-system'],
        ];
        for (const [files, profile] of cases) {
            const rendered = services(render(files, profile), `${profile} ${files.join(',')}`);
            assert.equal(Object.values(rendered).some((service) => service.ports?.length), true);
            for (const service of Object.values(rendered)) {
                for (const port of service.ports || []) {
                    assert.equal(port.host_ip, '127.0.0.1');
                }
                for (const volume of service.volumes || []) {
                    if (volume.target === '/docker-entrypoint-initdb.d') {
                        assert.equal(volume.read_only, true);
                    }
                }
            }
        }
    });

    it('honors an explicit interface override', () => {
        const rendered = services(
            render(['docker-compose.dev.yaml'], undefined, { DOCKER_BIND_ADDRESS: '192.0.2.10' }),
            'bind override',
        );
        for (const service of Object.values(rendered)) {
            for (const port of service.ports || []) {
                assert.equal(port.host_ip, '192.0.2.10');
            }
        }
    });

    it('requires production credentials and keeps the database private', () => {
        const required = {
            CHURCHCRM_DB_NAME: 'churchcrm',
            CHURCHCRM_DB_USER: 'churchcrm',
            CHURCHCRM_DB_PASSWORD: 'test-app',
            CHURCHCRM_DB_ROOT_PASSWORD: 'test-root',
        };
        assert.notEqual(render(['production/compose.yaml']).status, 0);
        for (const omitted of Object.keys(required)) {
            const environment = { ...required };
            delete environment[omitted];
            assert.notEqual(render(['production/compose.yaml'], undefined, environment).status, 0, omitted);
        }
        const rendered = services(render(['production/compose.yaml'], undefined, required), 'production');
        assert.equal(rendered.db.ports?.length || 0, 0);
        for (const service of Object.values(rendered)) {
            for (const port of service.ports || []) {
                assert.equal(port.host_ip, '127.0.0.1');
            }
        }
    });

    it('stops an operator who copies .env.example unedited', () => {
        const values = {};
        for (const line of fs.readFileSync(path.join(dockerDir, 'production', '.env.example'), 'utf8').split('\n')) {
            const trimmed = line.trim();
            if (trimmed && !trimmed.startsWith('#') && trimmed.includes('=')) {
                const eq = trimmed.indexOf('=');
                values[trimmed.slice(0, eq)] = trimmed.slice(eq + 1);
            }
        }
        for (const key of ['CHURCHCRM_DB_PASSWORD', 'CHURCHCRM_DB_ROOT_PASSWORD']) {
            assert.equal(Object.hasOwn(values, key), true, key);
            assert.equal(values[key], '');
        }
        assert.notEqual(render(['production/compose.yaml'], undefined, values).status, 0);
    });

    it('requires both passwords in the production examples', () => {
        for (const filename of ['examples/docker-compose.nginx.yaml', 'examples/docker-compose.frankenphp.yaml']) {
            for (const environment of [{}, { MYSQL_ROOT_PASSWORD: 'test-root' }, { MYSQL_PASSWORD: 'test-app' }]) {
                assert.notEqual(render([filename], undefined, environment).status, 0, filename);
            }
            assert.equal(render([filename], undefined, {
                MYSQL_ROOT_PASSWORD: 'test-root',
                MYSQL_PASSWORD: 'test-app',
            }).status, 0, filename);
        }
    });
});
