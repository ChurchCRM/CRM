#!/usr/bin/env node

/**
 * A subdirectory install serves the app under a path such as /churchcrm. A browser URL that
 * starts at "/" or at "./" ignores that path. This check fails when source code hands such a
 * literal to a place the browser requests: fetch, $.ajax, url:, location, href, src, action,
 * header('Location'), CSS url(), and variables named *Url / *Href. Build the URL from window.CRM.root, SystemURLs::getRootPath() or
 * SystemURLs::assetVersioned().
 *
 * A line that is deliberately root-relative ends with  // subdir-ok: <reason>
 * Variables and concatenated URLs are not visible here; cypress/e2e/ui/navigation/
 * install-path-requests.spec.js covers them at run time.
 *
 * Cypress specs are scanned too, but with a narrower rule. Cypress resolves root-relative
 * paths in cy.request / cy.visit / cy.intercept against baseUrl, so those are fine. The scan
 * only flags constructions that bypass Cypress and discard the install path: a literal
 * root-relative first argument to new URL(), a zero-arg url() call passed to new URL()
 * (the password-reset race failure), a raw fetch('/…'), or a location.href assignment.
 * Use absoluteUrl() from cypress/support/urls.js for raw fetch and cy.task URLs.
 */

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const SCAN_DIRS = [
    { dir: 'webpack', cypress: false },
    { dir: 'src', cypress: false },
    { dir: 'cypress', cypress: true },
];
const SKIP = /(^|\/)(vendor|external|node_modules|locale|logs|Images|videos|screenshots|reports)(\/|$)|\/skin\/v2\/|\.min\.js$/;
const EXTENSIONS = /\.(php|twig|js|ts|tsx|scss|css)$/;
const ROUTES = 'api|admin|people|finance|groups|v2|event|kiosk|plugins|ministries|fundraiser|session|setup|portal|skin';

const SINK =
    '(?:\\bfetch\\s*\\(|\\$\\.(?:ajax|get|post|getJSON)\\s*\\(|\\burl\\s*:|window\\.location(?:\\.href)?\\s*=(?!=)|' +
    '\\blocation(?:\\.href)?\\s*=(?!=)|location\\.(?:assign|replace)\\s*\\(|window\\.open\\s*\\(|new\\s+URL\\s*\\(|' +
    '\\.(?:get|post|put|delete|patch)\\s*\\(|\\b(?:href|src|action)\\s*=\\s*)';
const ROOT_RELATIVE = new RegExp(`${SINK}\\s*(?:\\(\\s*)?["'\`]/(?!/)[A-Za-z]`);
const CSS_URL = /(?:background(?:-image)?|src|content|cursor|mask(?:-image)?|list-style(?:-image)?)\s*:[^;]*\burl\(\s*["']?\/(?!\/)[A-Za-z]/;
const URL_VARIABLE = new RegExp(`\\b\\w*(?:Url|URL|Href)\\s*=\\s*["'\`]/(?:${ROUTES}|Images)/`);
const HEADER_LOCATION = /header\s*\(\s*["']Location:\s*\/(?!\/)[A-Za-z]/;
const DOT_RELATIVE = new RegExp(`(?:\\bfetch\\s*\\(|\\burl\\s*:|\\$\\.(?:ajax|get|post|getJSON)\\s*\\(|location(?:\\.href)?\\s*=(?!=))\\s*["'\`](?:\\.{1,2}/|(?:${ROUTES})/)`);
const SAFE_ON_LINE = /CRM\.root|getRootPath|assetVersioned|rootPath|subdir-ok:/;
const ROUTE_REGISTRATION = /\$(?:app|group)->(?:get|post|put|delete|patch|group)\(/;

// Cypress resolves these against baseUrl. Only flag constructions that discard the install path.
const CYPRESS_NEW_URL_LITERAL = /new\s+URL\s*\(\s*["'`]\/(?!\/)[A-Za-z]/;
const CYPRESS_NEW_URL_CALL = /new\s+URL\s*\(\s*url\s*\(\s*\)\s*,/;
const CYPRESS_RAW_FETCH = /\bfetch\s*\(\s*["'`]\/(?!\/)[A-Za-z]/;
const CYPRESS_LOCATION = /(?:window\.)?location(?:\.href)?\s*=\s*["'`]\/(?!\/)[A-Za-z]/;

function isComment(text) {
    return /^(\/\/|\*|\/\*|#)/.test(text);
}

function violations(source) {
    return source.split('\n').flatMap((line, index) => {
        const text = line.trim();
        if (isComment(text) || SAFE_ON_LINE.test(line) || ROUTE_REGISTRATION.test(line)) {
            return [];
        }
        return ROOT_RELATIVE.test(line) || HEADER_LOCATION.test(line) || CSS_URL.test(line) || URL_VARIABLE.test(line) || DOT_RELATIVE.test(line) ? [{ line: index + 1, text }] : [];
    });
}

function cypressViolations(source) {
    return source.split('\n').flatMap((line, index) => {
        const text = line.trim();
        if (isComment(text) || SAFE_ON_LINE.test(line)) {
            return [];
        }
        const dangerous =
            CYPRESS_NEW_URL_LITERAL.test(line) ||
            CYPRESS_NEW_URL_CALL.test(line) ||
            CYPRESS_RAW_FETCH.test(line) ||
            CYPRESS_LOCATION.test(line);
        return dangerous ? [{ line: index + 1, text }] : [];
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
    return SCAN_DIRS.flatMap(({ dir, cypress }) => sourceFiles(path.join(repo, dir)).flatMap((file) =>
        (cypress ? cypressViolations : violations)(fs.readFileSync(file, 'utf8')).map((hit) => ({ file: path.relative(repo, file), ...hit })),
    ));
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

module.exports = { scan, violations, cypressViolations };
