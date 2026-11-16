const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { test } = require('node:test');
const { findNativeDialogs } = require('../../scripts/lint/validate-native-dialogs');

test('rejects native dialogs and direct bootbox confirm/alert, including inline handlers', () => {
    for (const source of [
        'confirm("Delete?")',
        'window.confirm ("Delete?")',
        'window?.confirm("Delete?")',
        'window /* note */ . confirm("Delete?")',
        'confirm?.("Delete?")',
        'globalThis.alert("Failed")',
        'self.alert("Failed")',
        '<form onsubmit="return confirm(<?= $message ?>)">',
        'alert\n("Failed")',
        'prompt("Copy", text)',
        '// confirm-action-exempt: reason\nwindow.confirm("Delete?")',
        'bootbox.prompt({ title: \'Name\' })',
        'bootbox.confirm({ message: \'Delete?\' })',
        'window.bootbox?.alert("Failed")',
    ]) {
        assert.equal(findNativeDialogs(source).length, 1, source);
    }
});

test('allows the wrappers, bootbox input dialogs, exempt calls and comments', () => {
    assert.deepEqual(findNativeDialogs(`
        // window.confirm('example')
        /* alert('example') */
        bootbox.dialog({ title: 'Form' });
        // confirm-action-exempt: reason
        bootbox.confirm({ message: 'Delete?' });
        alertAction('Done', 'Saved');
        window.CRM.notify('Saved');
        confirmAction('Delete', 'Delete?', remove);
    `), []);
});

test('reports line numbers and fails the CLI against a bad input', () => {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'native-dialogs-'));
    try {
        const file = path.join(dir, 'bad.js');
        fs.writeFileSync(file, '// example\nwindow.confirm("Delete?");\n');
        const result = spawnSync(process.execPath, ['scripts/lint/validate-native-dialogs.js', file], { encoding: 'utf8' });
        assert.equal(result.status, 1);
        assert.match(result.stderr, /bad\.js:2:/);
    } finally {
        fs.rmSync(dir, { recursive: true, force: true });
    }
});
