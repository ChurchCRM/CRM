const assert = require('node:assert/strict');
const { test } = require('node:test');
const { scan, violations } = require('./validate-subdir-urls');

const flagged = (code) => violations(code).length;

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
    ].forEach((code) => assert.equal(flagged(code), 0, code));
});

test('the source tree has no violations', () => {
    assert.deepEqual(scan(), []);
});
