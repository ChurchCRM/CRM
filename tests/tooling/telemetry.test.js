const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../webpack/telemetry.js'), 'utf8').replace('import posthog from "posthog-js";', '');

for (const profile of ['production', 'demo', 'test']) {
    test(`browser telemetry ${profile}`, () => {
        const calls = [];
        const properties = [];
        vm.runInNewContext(source, {
            window: { CRM: { root: '', version: '7.8.3', telemetry: { key: 'test-key', endpoint: 'https://example.invalid', level: 'full', install_profile: profile } } },
            posthog: {
                init: (...args) => calls.push(args),
                register: (value) => properties.push(value),
            },
        });
        const suppressed = profile === 'test';
        assert.equal(calls.length, suppressed ? 0 : 1);
        assert.equal(properties.length, suppressed ? 0 : 1);
        if (!suppressed) {
            assert.equal(properties[0].install_profile, profile);
            assert.equal(properties[0].crm_version, '7.8.3');
            assert.equal(calls[0][1].person_profiles, 'never');
        }
    });
}
