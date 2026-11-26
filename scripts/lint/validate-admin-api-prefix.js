#!/usr/bin/env node

/**
 * Admin-only routes belong on the /admin app.
 *
 * A route file under src/api is served at /api. AdminRoleAuthMiddleware
 * there puts an admin operation on a prefix that clients and proxies treat
 * as ordinary API. The route belongs in src/admin, where it is /admin/api.
 */

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..', '..');
const apiRoot = path.join(root, 'src', 'api');

function stripComments(source) {
    return source
        .replace(/\/\*[\s\S]*?\*\//g, (block) => block.replace(/[^\n]/g, ' '))
        .replace(/(^|[^:])\/\/.*$/gm, '$1');
}

function findAdminRoleGates(source) {
    const stripped = stripComments(source);
    const hits = [];
    for (const match of stripped.matchAll(/\bAdminRoleAuthMiddleware\b/g)) {
        hits.push({ line: stripped.slice(0, match.index).split('\n').length });
    }
    return hits;
}

function phpFiles(dir, out = []) {
    if (!fs.existsSync(dir)) {
        return out;
    }
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            phpFiles(full, out);
        } else if (entry.isFile() && entry.name.endsWith('.php')) {
            out.push(full);
        }
    }
    return out;
}

function filesToScan(target) {
    const stat = fs.statSync(target);
    if (stat.isDirectory()) {
        return phpFiles(target);
    }
    return target.endsWith('.php') ? [target] : [];
}

if (require.main === module) {
    const targets = process.argv.length > 2 ? process.argv.slice(2) : [apiRoot];
    let failures = 0;
    let scanned = 0;
    for (const target of targets) {
        for (const file of filesToScan(target)) {
            scanned++;
            for (const hit of findAdminRoleGates(fs.readFileSync(file, 'utf8'))) {
                const where = path.isAbsolute(file) ? path.relative(root, file) : file;
                console.error(
                    `${where}:${hit.line}: AdminRoleAuthMiddleware belongs in src/admin (served at /admin), not on the /api app.`,
                );
                failures++;
            }
        }
    }
    if (failures === 0) {
        console.log(`No AdminRoleAuthMiddleware on the /api app (${scanned} PHP file(s)).`);
    }
    process.exitCode = failures ? 1 : 0;
}

module.exports = { findAdminRoleGates };
