const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { test } = require('node:test');

const mysql = path.join(__dirname, '..', '..', 'src', 'mysql');
const steps = Object.values(JSON.parse(fs.readFileSync(path.join(mysql, 'upgrade.json'), 'utf8')));
const MOVE = '/mysql/upgrade/7.8.3-member-sign-in.sql';
const DROP = '/mysql/upgrade/7.9.0-drop-fam-email.sql';

const scriptsFrom = (version) => {
    const scripts = [];
    for (let step = steps.find((s) => s.versions.includes(version)); step; step = steps.find((s) => s.versions.includes(step.dbVersion))) {
        scripts.push(...step.scripts);
    }
    return scripts;
};

const atLeast783 = (version) => {
    const [a, b, c] = version.split('.').map(Number);
    return a * 1e6 + b * 1e3 + (c || 0) >= 7008003;
};

test('every upgrade path from before 7.8.3 runs the 7.8.3 move before fam_Email is dropped', () => {
    for (const version of steps.flatMap((s) => s.versions)) {
        const scripts = scriptsFrom(version);
        assert.ok(scripts.includes(DROP), `${version} never reaches ${DROP}`);
        if (!atLeast783(version)) {
            const move = scripts.indexOf(MOVE);
            assert.ok(move > -1 && move < scripts.indexOf(DROP), `${version} drops fam_Email without the 7.8.3 move`);
        }
    }
});

test('the 7.9.0 move marks the address unconfirmed before copying it to the person', () => {
    const sql = fs.readFileSync(path.join(mysql, '..', DROP), 'utf8');
    const mark = sql.indexOf("'family_email', @fem_run");
    const copy = sql.indexOf('SET p.`per_Email` = m.`fem_Email`');
    assert.ok(mark > -1 && copy > -1 && mark < copy);
});
