const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const os = require('os');
const path = require('path');
const zlib = require('zlib');
const { prepare } = require('../../docker/prepare-release-context');

const S_IFLNK = 0o120000;

function u16(value) {
    const buffer = Buffer.alloc(2);
    buffer.writeUInt16LE(value);
    return buffer;
}

function u32(value) {
    const buffer = Buffer.alloc(4);
    buffer.writeUInt32LE(value >>> 0);
    return buffer;
}

function zip(entries) {
    const locals = [];
    const central = [];
    let offset = 0;
    for (const entry of entries) {
        const name = Buffer.from(entry.name);
        const raw = Buffer.from(entry.data ?? '');
        const data = entry.deflate ? zlib.deflateRawSync(raw) : raw;
        const method = entry.deflate ? 8 : 0;
        const crc = entry.badCrc ? 1 : zlib.crc32(raw);
        const local = Buffer.concat([
            u32(0x04034b50), u16(20), u16(0), u16(method), u16(0), u16(0),
            u32(crc), u32(data.length), u32(raw.length), u16(name.length), u16(0), name, data,
        ]);
        locals.push(local);
        central.push(Buffer.concat([
            u32(0x02014b50), u16(20), u16(20), u16(0), u16(method), u16(0), u16(0),
            u32(crc), u32(data.length), u32(raw.length), u16(name.length), u16(0), u16(0),
            u16(0), u16(0), u32(entry.externalAttr ?? 0), u32(offset), name,
        ]));
        offset += local.length;
    }
    const directory = Buffer.concat(central);
    const eocd = Buffer.concat([
        u32(0x06054b50), u16(0), u16(0), u16(entries.length), u16(entries.length),
        u32(directory.length), u32(offset), u16(0),
    ]);
    return Buffer.concat([...locals, directory, eocd]);
}

function requiredEntries(extra) {
    const entries = ['index.php', '.htaccess', 'vendor/autoload.php'].map((name) => ({
        name: `churchcrm/${name}`,
        data: name,
    }));
    if (extra) {
        entries.push(extra);
    }
    return entries;
}

function withTemp(fn) {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'release-context-'));
    try {
        return fn(root);
    } finally {
        fs.rmSync(root, { recursive: true, force: true });
    }
}

describe('prepare-release-context', () => {
    it('preserves hidden files without the wrapper directory', () => {
        withTemp((root) => {
            const archive = path.join(root, 'release.zip');
            fs.writeFileSync(archive, zip(requiredEntries()));
            prepare(archive, path.join(root, 'context'));
            assert.equal(fs.readFileSync(path.join(root, 'context', '.htaccess'), 'utf8'), '.htaccess');
            assert.equal(fs.existsSync(path.join(root, 'context', 'churchcrm')), false);
        });
    });

    it('inflates a deflated release', () => {
        withTemp((root) => {
            const archive = path.join(root, 'release.zip');
            const entries = requiredEntries().map((entry) => ({ ...entry, deflate: true }));
            fs.writeFileSync(archive, zip(entries));
            prepare(archive, path.join(root, 'context'));
            assert.equal(fs.readFileSync(path.join(root, 'context', 'index.php'), 'utf8'), 'index.php');
        });
    });

    it('rejects unsafe paths and credentials before extraction', () => {
        for (const name of [
            'churchcrm/../escaped',
            '/absolute',
            'other/index.php',
            'churchcrm/Include/Config.php',
            'churchcrm/nested/.npmrc',
            'churchcrm/Include/./Config.php',
        ]) {
            withTemp((root) => {
                const archive = path.join(root, 'release.zip');
                fs.writeFileSync(archive, zip(requiredEntries({ name, data: 'secret' })));
                assert.throws(() => prepare(archive, path.join(root, 'context')), /Unsafe archive path|Expected churchcrm|deployment credentials/);
                assert.equal(fs.existsSync(path.join(root, 'context')), false);
            });
        }
    });

    it('rejects an entry whose CRC does not match its bytes', () => {
        withTemp((root) => {
            const archive = path.join(root, 'release.zip');
            const entries = requiredEntries();
            entries[0].badCrc = true;
            fs.writeFileSync(archive, zip(entries));
            assert.throws(() => prepare(archive, path.join(root, 'context')), /CRC/);
        });
    });

    it('rejects symlinks', () => {
        withTemp((root) => {
            const archive = path.join(root, 'release.zip');
            fs.writeFileSync(archive, zip(requiredEntries({
                name: 'churchcrm/link',
                data: '/etc/passwd',
                externalAttr: ((S_IFLNK | 0o777) << 16) >>> 0,
            })));
            assert.throws(() => prepare(archive, path.join(root, 'context')), /special files/);
        });
    });

    it('rejects an incomplete archive', () => {
        withTemp((root) => {
            const archive = path.join(root, 'release.zip');
            fs.writeFileSync(archive, zip([{ name: 'churchcrm/index.php', data: 'test' }]));
            assert.throws(() => prepare(archive, path.join(root, 'context')), /missing required/);
        });
    });

    it('refuses to overlay an existing context', () => {
        withTemp((root) => {
            const archive = path.join(root, 'release.zip');
            fs.writeFileSync(archive, zip(requiredEntries()));
            assert.throws(() => prepare(archive, root), /must be empty/);
        });
    });
});
