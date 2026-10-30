const assert = require('node:assert/strict');
const { test } = require('node:test');
const { scan, violations, cypressViolations } = require('./validate-subdir-urls');

const flagged = (code) => violations(code).length;
const cypressFlagged = (code) => cypressViolations(code).length;

test('flags a root-relative URL handed to the browser', () => {
    [
        "fetch('/api/person/1');",
        'window.location.href = "/people/list";',
        "$.ajax({ url: '/api/x' });",
        'const r = await fetch(`/api/${id}`);',
        '<a href="/people/list">People</a>',
        '<script>fetch("/api/y")</script>',
        '<form action="/finance/pledge">',
        'header("Location: /v2/dashboard");',
        "$.get('/api/cart/');",
        "new URL('/api/x', window.location.origin);",
        "background: url('/Images/login-photo.png') center/cover;",
        'const personViewUrl = `/people/view/${id}`;',
    ].forEach((code) => assert.equal(flagged(code), 1, code));
});

test('flags a path relative to the current page', () => {
    ["url: './SystemPrerequisiteCheck',", "fetch('../api/x');", "fetch('api/persons/search/')"].forEach((code) =>
        assert.equal(flagged(code), 1, code),
    );
});

test('accepts URLs built from the install path', () => {
    [
        "fetch(window.CRM.root + '/api/person/1');",
        '<a href="<?= SystemURLs::getRootPath() ?>/people/list">',
        "<script src=\"<?= SystemURLs::assetVersioned('/skin/v2/x.min.js') ?>\">",
        'url: `${rootPath}/setup/SystemIntegrityCheck`,',
        "window.location.href = '/legacy'; // subdir-ok: external redirect target",
    ].forEach((code) => assert.equal(flagged(code), 0, code));
});

test('ignores comments, route registrations and values that are not URLs', () => {
    [
        "// fetch('/api/person/1')",
        ' * href="/people/list"',
        "$app->get('/people/list', 'handler');",
        "$group->post('/api/x', $handler);",
        "const name = form.get('name');",
        "const known = map.get('key');",
        "fetch('https://example.com/api');",
        "fetch('//cdn.example.com/x.js');",
        "background: url('../Images/login-photo.png');",
        'background: url(data:image/png;base64,AAAA);',
        'const personViewUrl = `${window.CRM.root}/people/view/${id}`;',
    ].forEach((code) => assert.equal(flagged(code), 0, code));
});

test('flags Cypress constructions that discard the install path', () => {
    [
        "new URL('/session/forgot-password/set/' + token, Cypress.config('baseUrl')).href",
        'new URL(url(), Cypress.config("baseUrl")).href',
        "fetch('/api/person/1')",
        "window.location.href = '/people/list'",
    ].forEach((code) => assert.equal(cypressFlagged(code), 1, code));
});

test('accepts Cypress requests that Cypress resolves against baseUrl', () => {
    [
        "cy.request({ url: '/api/person/1' })",
        "cy.visit('/people/list')",
        'cy.intercept("GET", "/api/x")',
        'new URL(url().replace(/^\\//, ""), Cypress.config("baseUrl")).href',
        'absoluteUrl(url())',
        'absoluteUrl("/api/background/timerjobs")',
        'new URL(response.body.url, Cypress.config("baseUrl")).href',
        "new URL(redirectResponse.headers.location, Cypress.config('baseUrl')).href",
        "fetch(window.CRM.root + '/api/person/1')",
        "window.location.href = '/legacy'; // subdir-ok: external",
    ].forEach((code) => assert.equal(cypressFlagged(code), 0, code));
});

test('the source tree has no violations', () => {
    assert.deepEqual(scan(), []);
});
