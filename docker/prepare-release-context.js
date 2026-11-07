#!/usr/bin/env node

/**
 * Extract a packaged ChurchCRM release, preserving dotfiles and rejecting links.
 * Usage: node docker/prepare-release-context.js ARCHIVE DESTINATION
 */

const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const S_IFMT = 0o170000;
const S_IFREG = 0o100000;
const S_IFDIR = 0o040000;
const REQUIRED = ['index.php', '.htaccess', 'vendor/autoload.php'];
const CREDENTIALS = new Set(['.env', '.npmrc', '.netrc', 'auth.json']);

function fail(message) {
    const error = new Error(message);
    error.name = 'ValueError';
    throw error;
}

function entries(buffer) {
    const eocd = buffer.lastIndexOf(Buffer.from([0x50, 0x4b, 0x05, 0x06]));
    if (eocd < 0) {
        fail('Not a zip archive');
    }
    const count = buffer.readUInt16LE(eocd + 10);
    let offset = buffer.readUInt32LE(eocd + 16);
    const found = [];
    for (let index = 0; index < count; index++) {
        if (buffer.readUInt32LE(offset) !== 0x02014b50) {
            fail('Not a zip archive');
        }
        const method = buffer.readUInt16LE(offset + 10);
        const crc = buffer.readUInt32LE(offset + 16);
        const compressedSize = buffer.readUInt32LE(offset + 20);
        const uncompressedSize = buffer.readUInt32LE(offset + 24);
        const nameLength = buffer.readUInt16LE(offset + 28);
        const extraLength = buffer.readUInt16LE(offset + 30);
        const commentLength = buffer.readUInt16LE(offset + 32);
        const externalAttr = buffer.readUInt32LE(offset + 38);
        const localOffset = buffer.readUInt32LE(offset + 42);
        const name = buffer.toString('utf8', offset + 46, offset + 46 + nameLength);
        found.push({ name, method, crc, compressedSize, uncompressedSize, externalAttr, localOffset });
        offset += 46 + nameLength + extraLength + commentLength;
    }
    return found;
}

function payload(buffer, entry) {
    const local = entry.localOffset;
    if (buffer.readUInt32LE(local) !== 0x04034b50) {
        fail('Not a zip archive');
    }
    const nameLength = buffer.readUInt16LE(local + 26);
    const extraLength = buffer.readUInt16LE(local + 28);
    const start = local + 30 + nameLength + extraLength;
    const data = buffer.subarray(start, start + entry.compressedSize);
    let inflated = data;
    if (entry.method === 8) {
        inflated = zlib.inflateRawSync(data);
    } else if (entry.method !== 0) {
        fail('Unsupported compression method');
    }
    if (inflated.length !== entry.uncompressedSize || zlib.crc32(inflated) !== entry.crc) {
        fail('Archive entry failed its CRC');
    }
    return inflated;
}

function prepare(archivePath, destination) {
    if (fs.existsSync(destination) && fs.readdirSync(destination).length > 0) {
        fail('Build context must be empty');
    }
    const buffer = fs.readFileSync(archivePath);
    const planned = [];
    for (const entry of entries(buffer)) {
        if (entry.name.includes('\\') || path.posix.isAbsolute(entry.name)) {
            fail('Unsafe archive path');
        }
        const normalized = path.posix.normalize(entry.name);
        const parts = normalized.split('/').filter((part) => part !== '');
        if (parts.includes('..')) {
            fail('Unsafe archive path');
        }
        if (parts.length === 0 || parts[0] !== 'churchcrm') {
            fail('Expected churchcrm/ release prefix');
        }
        const kind = (entry.externalAttr >>> 16) & S_IFMT;
        if (kind !== 0 && kind !== S_IFREG && kind !== S_IFDIR) {
            fail('Archive links and special files are not allowed');
        }
        if (parts.length > 1) {
            planned.push({ entry, relative: parts.slice(1).join(path.sep), directory: normalized.endsWith('/') });
        }
    }
    const names = new Set(planned.filter((item) => !item.directory).map((item) => item.relative.split(path.sep).join('/')));
    if (!REQUIRED.every((name) => names.has(name))) {
        fail('Release is missing required application files');
    }
    for (const name of names) {
        const base = path.posix.basename(name);
        if (name === 'Include/Config.php' || CREDENTIALS.has(base) || base.startsWith('.env.')) {
            fail('Release contains deployment credentials');
        }
    }
    fs.mkdirSync(destination, { recursive: true });
    for (const item of planned) {
        const target = path.join(destination, item.relative);
        if (item.directory) {
            fs.mkdirSync(target, { recursive: true });
        } else {
            fs.mkdirSync(path.dirname(target), { recursive: true });
            fs.writeFileSync(target, payload(buffer, item.entry));
        }
    }
}

if (require.main === module) {
    if (process.argv.length !== 4) {
        console.error('Usage: prepare-release-context.js ARCHIVE DESTINATION');
        process.exit(1);
    }
    try {
        prepare(process.argv[2], process.argv[3]);
    } catch (error) {
        console.error(error.message);
        process.exit(1);
    }
}

module.exports = { prepare };
