const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

function fixture(t) {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'churchcrm-persistence-'));
    t.after(() => fs.rmSync(root, { recursive: true, force: true }));
    const app = path.join(root, 'app');
    const data = path.join(root, 'data');
    const bin = path.join(root, 'bin');
    fs.mkdirSync(path.join(app, 'Include'), { recursive: true });
    fs.mkdirSync(data);
    fs.mkdirSync(bin);
    for (const kind of ['Person', 'Family']) {
        fs.mkdirSync(path.join(app, 'Images', kind), { recursive: true });
        fs.writeFileSync(path.join(app, 'Images', kind, '.htaccess'), 'Require all denied\n');
        fs.writeFileSync(path.join(app, 'Images', kind, '.gitkeep'), '');
    }
    fs.writeFileSync(path.join(app, 'Include', 'Functions.php'), 'new application code');
    fs.writeFileSync(path.join(app, 'Include', 'LoadConfigs.php'), 'current bootstrap');
    fs.writeFileSync(path.join(bin, 'docker-php-entrypoint'), '#!/bin/sh\nprintf "%s\\n" "$@"\n', { mode: 0o755 });
    const script = fs.readFileSync(path.join(__dirname, '../../docker/apache/persistent-entrypoint.sh'), 'utf8')
        .replace('/var/www/html', app).replace('/var/lib/churchcrm', data);
    const run = (enabled = '1') => spawnSync('sh', ['-c', script, 'entrypoint', 'apache2-foreground'], {
        encoding: 'utf8', env: { ...process.env, PATH: `${bin}:${process.env.PATH}`, CHURCHCRM_PERSISTENT_DATA: enabled },
    });
    return { app, data, run };
}

test('fresh setup writes config and photos to disk across container replacement', (t) => {
    const { app, data, run } = fixture(t);
    assert.equal(run().status, 0);
    assert.equal(fs.existsSync(path.join(app, 'Include', 'Config.php')), false);
    fs.writeFileSync(path.join(app, 'Include', 'Config.php'), '<?php // wizard config');
    fs.writeFileSync(path.join(app, 'Images', 'Person', 'photo.jpg'), 'person photo');
    fs.writeFileSync(path.join(app, 'Images', 'Family', 'photo.jpg'), 'family photo');
    for (const relative of ['Include/Config.php', 'Images/Person', 'Images/Family']) {
        fs.unlinkSync(path.join(app, relative));
    }
    for (const kind of ['Person', 'Family']) {
        fs.mkdirSync(path.join(app, 'Images', kind));
        fs.writeFileSync(path.join(app, 'Images', kind, '.htaccess'), 'Require all denied\n# refreshed');
    }
    assert.equal(run().status, 0);
    assert.equal(fs.readFileSync(path.join(data, 'Config.php'), 'utf8'), '<?php // wizard config');
    assert.equal(fs.readFileSync(path.join(app, 'Images/Person/photo.jpg'), 'utf8'), 'person photo');
    assert.equal(fs.readFileSync(path.join(app, 'Images/Family/photo.jpg'), 'utf8'), 'family photo');
    assert.match(fs.readFileSync(path.join(data, 'Person/.htaccess'), 'utf8'), /refreshed/);
    assert.equal(fs.readFileSync(path.join(app, 'Include/Functions.php'), 'utf8'), 'new application code');
    assert.equal(fs.realpathSync(path.join(data, 'LoadConfigs.php')), path.join(app, 'Include/LoadConfigs.php'));
    assert.equal(run().status, 0);
});

test('default startup leaves existing installations alone and forwards the command', (t) => {
    const { app, run } = fixture(t);
    fs.writeFileSync(path.join(app, 'Include/Config.php'), 'existing config');
    const result = run('0');
    assert.equal(result.status, 0);
    assert.equal(result.stdout, 'apache2-foreground\n');
    assert.equal(fs.lstatSync(path.join(app, 'Include/Config.php')).isSymbolicLink(), false);
});

test('opt-in refuses to overwrite an existing configuration', (t) => {
    const { app, run } = fixture(t);
    fs.writeFileSync(path.join(app, 'Include/Config.php'), 'existing config');
    assert.notEqual(run().status, 0);
    assert.equal(fs.readFileSync(path.join(app, 'Include/Config.php'), 'utf8'), 'existing config');
});

test('opt-in refuses to discard existing photos', (t) => {
    const { app, run } = fixture(t);
    fs.writeFileSync(path.join(app, 'Images/Person/photo.jpg'), 'existing photo');
    assert.notEqual(run().status, 0);
    assert.equal(fs.readFileSync(path.join(app, 'Images/Person/photo.jpg'), 'utf8'), 'existing photo');
});

test('an unwritable persistent disk fails with an ownership instruction', (t) => {
    if (process.getuid?.() === 0) {
        t.skip('root bypasses filesystem write permissions');
        return;
    }
    const { data, run } = fixture(t);
    fs.chmodSync(data, 0o555);
    const result = run();
    assert.notEqual(result.status, 0);
    assert.match(result.stderr, /writable by UID 33/);
});

test('photo directories without shipped access rules remain private', (t) => {
    const { app, data, run } = fixture(t);
    for (const kind of ['Person', 'Family']) {
        fs.unlinkSync(path.join(app, 'Images', kind, '.htaccess'));
    }
    assert.equal(run().status, 0);
    for (const kind of ['Person', 'Family']) {
        assert.equal(fs.readFileSync(path.join(data, kind, '.htaccess'), 'utf8'), 'Require all denied\n');
    }
});
