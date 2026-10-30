const assert = require('node:assert/strict');
const { test } = require('node:test');
const { gates, injections } = require('./validate-pr-gates');

test('fails a schema change without an upgrade script and upgrade.json', () => {
    assert.equal(gates(['orm/schema.xml']).fail.length, 1);
    assert.equal(gates(['src/mysql/install/Install.sql', 'src/mysql/upgrade/7.9.0.sql']).fail.length, 1);
    assert.equal(gates(['orm/schema.xml', 'src/mysql/upgrade/7.9.0.sql', 'src/mysql/upgrade.json']).fail.length, 0);
});

test('warns on behavior without a spec and accepts a covering spec', () => {
    assert.equal(gates(['src/people/views/list.php']).warn.some((m) => m.startsWith('Behavior changed')), true);
    assert.equal(gates(['webpack/event-checkin.js', 'cypress/e2e/ui/x.spec.js']).warn.some((m) => m.startsWith('Behavior changed')), false);
    assert.equal(gates(['src/mysql/upgrade/7.9.0.sql']).warn.length, 0);
});

test('warns on a workflow without a script test', () => {
    assert.equal(gates(['.github/workflows/a.yml']).warn.some((m) => m.startsWith('Workflow')), true);
    assert.equal(gates(['.github/workflows/a.yml', 'scripts/a.test.js']).warn.some((m) => m.startsWith('Workflow')), false);
});

test('reminds about the UI pass for skin, webpack and template files', () => {
    ['src/skin/scss/_x.scss', 'webpack/a.js', 'src/v2/templates/a.twig'].forEach((file) =>
        assert.equal(gates([file]).warn.some((m) => m.startsWith('UI files')), true, file),
    );
});

test('flags untrusted context interpolated into a run script', () => {
    const bad = ['steps:', '  - run: |', '      echo "${{ github.event.pull_request.title }}"'].join('\n');
    const inline = '  - run: echo ${{ github.head_ref }}';
    assert.equal(injections(bad).length, 1);
    assert.equal(injections(inline).length, 1);
});

test('accepts env indirection, trusted context and an inject-ok marker', () => {
    const env = ['  - env:', '      TITLE: ${{ github.event.pull_request.title }}', '    run: |', '      echo "$TITLE"'].join('\n');
    const trusted = ['  - run: |', '      echo "${{ github.event.number }} ${{ github.sha }}"'].join('\n');
    const marked = ['  - run: |', '      echo "${{ github.head_ref }}" # inject-ok: validated earlier'].join('\n');
    [env, trusted, marked].forEach((source) => assert.equal(injections(source).length, 0, source));
});

test('stops scanning a run block at the next key', () => {
    const source = ['  - run: |', '      echo hi', '    with:', '      title: ${{ github.event.pull_request.title }}'].join('\n');
    assert.equal(injections(source).length, 0);
});
