const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { test } = require('node:test');
const vm = require('node:vm');
const ts = require('typescript');

const source = fs.readFileSync(path.join(__dirname, '../../webpack/api-utils.ts'), 'utf8');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText;

function load() {
    const notices = [];
    const context = { exports: {}, Response, window: { CRM: { notify: (message, options) => notices.push({ message, options }) } } };
    vm.runInNewContext(compiled, context);
    return { notifyAPIError: context.exports.notifyAPIError, notices };
}

const danger = { type: 'danger', delay: 5000 };

test('shows the server message from a fetch Response', async () => {
    const { notifyAPIError, notices } = load();
    await notifyAPIError(new Response(JSON.stringify({ message: 'Group not found' }), { status: 404 }), 'Fallback');
    assert.deepEqual(JSON.parse(JSON.stringify(notices)), [{ message: 'Group not found', options: danger }]);
});

test('falls back when a Response body is not JSON or has no message', async () => {
    const { notifyAPIError, notices } = load();
    await notifyAPIError(new Response('<html>oops</html>', { status: 500 }), 'Fallback');
    await notifyAPIError(new Response('{}', { status: 500 }), 'Fallback');
    assert.deepEqual(notices.map((n) => n.message), ['Fallback', 'Fallback']);
});

test('shows the server message from a jqXHR and falls back without one', async () => {
    const { notifyAPIError, notices } = load();
    await notifyAPIError({ responseJSON: { message: 'Role in use' } }, 'Fallback');
    await notifyAPIError({ responseJSON: { message: '' } }, 'Fallback');
    await notifyAPIError({}, 'Fallback');
    await notifyAPIError(undefined, 'Fallback');
    assert.deepEqual(notices.map((n) => n.message), ['Role in use', 'Fallback', 'Fallback', 'Fallback']);
});
