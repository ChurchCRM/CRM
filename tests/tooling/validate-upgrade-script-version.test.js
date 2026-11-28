const assert = require('node:assert/strict');
const { test } = require('node:test');
const { checkUpgradeScriptVersions } = require('../../scripts/lint/validate-upgrade-script-version.js');

const json = { 'pre-7.8.4': { versions: ['7.8.3'], scripts: [] }, current: { versions: ['7.8.8'], scripts: [], dbVersion: '7.9.0' } };
const check = (changedFiles) => checkUpgradeScriptVersions({ addedFiles: [], changedFiles, baseJson: json, headJson: json });

test('refuses an edit to a shipped upgrade script', () => {
    const [problem, ...rest] = check(['src/mysql/upgrade/7.8.3-member-sign-in.sql']);
    assert.equal(rest.length, 0);
    assert.match(problem, /7\.8\.3-member-sign-in\.sql has shipped and is frozen/);
    assert.match(problem, /new 7\.9\.0 script/);
});

test('allows edits to the next release script and to files outside the upgrade folder', () => {
    assert.deepEqual(check(['src/mysql/upgrade/7.9.0-drop-fam-email.sql', 'src/mysql/install/Install.sql']), []);
});
