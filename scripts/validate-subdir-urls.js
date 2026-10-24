#!/usr/bin/env node

/**
 * A subdirectory install serves the app under a path such as /churchcrm. A browser URL that
 * starts at "/" or at "./" ignores that path. This check fails when source code hands such a
 * literal to a place the browser requests: fetch, $.ajax, url:, location, href, src, action,
 * header('Location'). Build the URL from window.CRM.root, SystemURLs::getRootPath() or
 * SystemURLs::assetVersioned().
 *
 * A line that is deliberately root-relative ends with  // subdir-ok: <reason>
 * Variables and concatenated URLs are not visible here; cypress/e2e/ui/navigation/
 * install-path-requests.spec.js covers them at run time.
 */

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const SCAN_DIRS = ['webpack', 'src'];
const SKIP = /(^|\/)(vendor|external|node_modules|locale|logs|Images)(\/|$)|\/skin\/v2\/|\.min\.js$/;
const EXTENSIONS = /\.(php|twig|js|ts|tsx)$/;
const ROUTES = 'api|admin|people|finance|groups|v2|event|kiosk|plugins|ministries|fundraiser|session|setup|portal|skin';

const SINK =
    '(?:\\bfetch\\s*\\(|\\$\\.(?:ajax|get|post|getJSON)\\s*\\(|\\burl\\s*:|window\\.location(?:\\.href)?\\s*=(?!=)|' +
    '\\blocation(?:\\.href)?\\s*=(?!=)|location\\.(?:assign|replace)\\s*\\(|window\\.open\\s*\\(|new\\s+URL\\s*\\(|' +
    '\\.(?:get|post|put|delete|patch)\\s*\\(|\\b(?:href|src|action)\\s*=\\s*)';
const ROOT_RELATIVE = new RegExp(`${SINK}\\s*(?:\\(\\s*)?["'\`]/(?!/)[A-Za-z]`);
const HEADER_LOCATION = /header\s*\(\s*["']Location:\s*\/(?!\/)[A-Za-z]/;
const DOT_RELATIVE = new RegExp(`(?:\\bfetch\\s*\\(|\\burl\\s*:|\\$\\.(?:ajax|get|post|getJSON)\\s*\\(|location(?:\\.href)?\\s*=(?!=))\\s*["'\`](?:\\.{1,2}/|(?:${ROUTES})/)`);
const SAFE_ON_LINE = /CRM\.root|getRootPath|assetVersioned|rootPath|subdir-ok:/;
const ROUTE_REGISTRATION = /\$(?:app|group)->(?:get|post|put|delete|patch|group)\(/;

function violations(source) {
    return source.split('\n').flatMap((line, index) => {
        const text = line.trim();
        if (/^(\/\/|\*|\/\*|#)/.test(text) || SAFE_ON_LINE.test(line) || ROUTE_REGISTRATION.test(line)) {
            return [];
        }
        return ROOT_RELATIVE.test(line) || HEADER_LOCATION.test(line) || DOT_RELATIVE.test(line) ? [{ line: index + 1, text }] : [];
    });
}

function sourceFiles(dir) {
    if (!fs.existsSync(dir)) {
        return [];
    }
    return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
        const full = path.join(dir, entry.name);
        const relative = path.relative(root, full).split(path.sep).join('/');
        if (SKIP.test(relative)) {
            return [];
        }
        return entry.isDirectory() ? sourceFiles(full) : EXTENSIONS.test(entry.name) ? [full] : [];
    });
}

function scan(repo = root) {
    return SCAN_DIRS.flatMap((dir) => sourceFiles(path.join(repo, dir))).flatMap((file) =>
        violations(fs.readFileSync(file, 'utf8')).map((hit) => ({ file: path.relative(repo, file), ...hit })),
    );
}

if (require.main === module) {
    const found = scan();
    found.forEach(({ file, line, text }) => console.error(`${file}:${line}  ${text}`));
    if (found.length) {
        console.error(
            `\n${found.length} URL(s) ignore the install path. Use window.CRM.root, SystemURLs::getRootPath() or assetVersioned(); mark a deliberate case with // subdir-ok: <reason>.`,
        );
    }
    process.exit(found.length ? 1 : 0);
}

module.exports = { scan, violations };
