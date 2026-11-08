const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { test } = require('node:test');
const vm = require('node:vm');
const ts = require('typescript');

const source = fs.readFileSync(path.join(__dirname, '../../webpack/common/confirm-action.ts'), 'utf8');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText;

function load(bootbox) {
    const context = { exports: {}, window: { bootbox }, i18next: { t: (key) => `translated ${key}` } };
    vm.runInNewContext(compiled, context);
    return context.exports.confirmAction;
}

test('only an accepted confirmation performs the action', () => {
    let options;
    let calls = 0;
    const confirmAction = load({ confirm: (value) => { options = value; } });
    confirmAction('Delete', 'Delete this record?', () => { calls++; });
    assert.equal(calls, 0);
    options.callback(false);
    assert.equal(calls, 0);
    confirmAction('Delete', 'Delete this record?', () => { calls++; });
    options.callback(true);
    assert.equal(calls, 1);
    assert.equal(options.buttons.confirm.className, 'btn-danger');
    assert.equal(options.buttons.confirm.label, 'translated Yes');
    assert.equal(options.buttons.cancel.label, 'translated No');
});

test('supports primary confirmations and preserves rich messages', () => {
    let options;
    load({ confirm: (value) => { options = value; } })('Assign', '<p>Assign volunteer?</p>', () => {}, false);
    assert.equal(options.buttons.confirm.className, 'btn-primary');
    assert.equal(options.message, '<p>Assign volunteer?</p>');
});

test('never performs the action when the modal library is unavailable', () => {
    let calls = 0;
    load(undefined)('Delete', 'Delete?', () => { calls++; });
    assert.equal(calls, 0);
});

test('runs onCancel when the dialog is declined or dismissed', () => {
    let options;
    let cancelled = 0;
    load({ confirm: (value) => { options = value; } })('Apply', 'Apply?', () => {}, { tone: "primary", onCancel: () => { cancelled++; } });
    options.callback(false);
    assert.equal(cancelled, 1);
    assert.equal(options.buttons.confirm.className, 'btn-primary');
});

test('answers once and closes a dialog answered while it is still fading in', () => {
    let options;
    let calls = 0;
    const hidden = [];
    const context = {
        exports: {},
        window: { bootbox: { confirm: (value) => { options = value; } }, bootstrap: { Modal: { getOrCreateInstance: (el) => ({ hide: () => hidden.push(el) }) } } },
        i18next: { t: (key) => key },
    };
    vm.runInNewContext(compiled, context);
    context.exports.confirmAction('Delete', 'Delete?', () => { calls++; });
    assert.equal(options.callback(true), false);
    assert.equal(options.callback(true), false);
    assert.equal(calls, 1);
    options.onShown({ target: 'modal' });
    assert.deepEqual(hidden, ['modal']);
});
